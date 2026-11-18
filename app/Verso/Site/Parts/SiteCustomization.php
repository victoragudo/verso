<?php

namespace App\Verso\Site\Parts;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Theme;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

// Export and import of the whole site customization as one JSON document:
// edited parts, header and footer styles, colors and fonts, and menus.
class SiteCustomization
{
    // Name written in the "format" field, so other JSON files are refused.
    public const FORMAT = 'verso-site-customization';

    public const VERSION = 1;

    public function __construct(
        protected SitePartStore $store,
        protected PartVariants $variants,
        protected GlobalStyles $styles,
        protected SyncMenuItems $syncMenuItems,
        protected MenuLocations $locations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function export(Theme $theme): array
    {
        $parts = [];

        foreach ($this->store->forTheme($theme->slug) as $key => $sitePart) {
            $parts[$key] = ['blocks' => $sitePart->blocks, 'published' => $sitePart->published_blocks];
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'theme' => $theme->slug,
            'exported_at' => Carbon::now()->toIso8601String(),
            'parts' => $parts,
            'part_styles' => $this->variants->choices($theme),
            'styles' => $this->styles->values($theme),
            'menus' => Menu::query()->orderBy('name')->get()->map(fn (Menu $menu): array => [
                'name' => $menu->name,
                'location' => $menu->location,
                'items' => $this->exportItems($menu->tree(['entry', 'term'])),
            ])->all(),
        ];
    }

    /**
     * Applies a customization to a theme. Everything is checked first; nothing is saved when one part is wrong.
     *
     * @param  array<mixed>  $data
     * @return array{parts: int, menus: int}
     *
     * @throws InvalidArgumentException when the document is not valid
     */
    public function import(Theme $theme, array $data, Authorizable $user, int $userId): array
    {
        if (($data['format'] ?? null) !== self::FORMAT || ! is_int($data['version'] ?? null) || $data['version'] > self::VERSION) {
            throw new InvalidArgumentException('This file is not a Verso site customization.');
        }

        $parts = $this->checkedParts($data['parts'] ?? [], $user);
        $menus = is_array($data['menus'] ?? null) ? array_values(array_filter($data['menus'], is_array(...))) : [];

        DB::transaction(function () use ($theme, $data, $parts, $menus, $userId): void {
            foreach ($parts as $key => $part) {
                if ($part['published'] !== null) {
                    $this->store->publish($theme->slug, $key, $part['published'], $userId);
                }

                $this->store->saveDraft($theme->slug, $key, $part['blocks'], $userId);
            }

            foreach (is_array($data['part_styles'] ?? null) ? $data['part_styles'] : [] as $name => $style) {
                if (is_string($name) && is_string($style)) {
                    $this->variants->choose($theme, $name, $style);
                }
            }

            if (is_array($data['styles'] ?? null)) {
                $this->styles->save($theme, $data['styles']);
            }

            foreach ($menus as $menu) {
                $this->importMenu($menu);
            }
        });

        return ['parts' => count($parts), 'menus' => count($menus)];
    }

    /**
     * @return array<string, array{blocks: array<int, mixed>, published: array<int, mixed>|null}>
     */
    protected function checkedParts(mixed $parts, Authorizable $user): array
    {
        if (! is_array($parts)) {
            throw new InvalidArgumentException('The parts of the file are not valid.');
        }

        $checked = [];

        foreach ($parts as $key => $part) {
            if (! is_string($key) || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) !== 1 || ! is_array($part)) {
                throw new InvalidArgumentException('The parts of the file are not valid.');
            }

            $blocks = $part['blocks'] ?? null;
            $published = $part['published'] ?? null;
            $validator = Validator::make(
                ['blocks' => $blocks, 'published' => $published],
                ['blocks' => ['present', 'array', new ValidBlocks($user)], 'published' => ['nullable', 'array', new ValidBlocks($user)]],
            );

            if ($validator->fails() || ! is_array($blocks)) {
                throw new InvalidArgumentException("The \"{$key}\" part has blocks that cannot be used: ".$validator->errors()->first());
            }

            $checked[$key] = ['blocks' => array_values($blocks), 'published' => is_array($published) ? array_values($published) : null];
        }

        return $checked;
    }

    /**
     * Menu items as plain data. Entries and terms keep their id and their link, so another site can use the link.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    protected function exportItems(Collection $items): array
    {
        return array_values($items->map(fn (MenuItem $item): array => [
            'type' => $item->type->value,
            'entry_id' => $item->entry_id,
            'term_id' => $item->term_id,
            'label' => $item->label ?? $item->resolvedLabel(),
            'url' => $item->url ?? $item->resolvedUrl(),
            'open_in_new_tab' => $item->open_in_new_tab,
            'mega' => $item->mega,
            'rel' => $item->rel === null ? [] : explode(' ', $item->rel),
            'visibility' => $item->visibility->value,
            'children' => $this->exportItems($item->children),
        ])->all());
    }

    // Replaces the menu with the same name, or creates it. Missing entries and terms become plain links.
    /** @param array<mixed> $data */
    protected function importMenu(array $data): void
    {
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';

        if ($name === '') {
            return;
        }

        $menu = Menu::query()->firstOrCreate(['name' => $name]);
        $location = is_string($data['location'] ?? null) && $this->locations->has($data['location']) ? $data['location'] : null;
        $menu->assignLocation($location);

        $items = $this->importItems(is_array($data['items'] ?? null) ? $data['items'] : [], 1);
        $this->syncMenuItems->handle($menu, $items);
    }

    /**
     * @param  array<mixed>  $items
     * @return list<array{type: string, entry_id?: int|null, term_id?: int|null, label?: string|null, url?: string|null, open_in_new_tab?: bool, mega?: bool, rel?: list<string>|null, visibility?: string|null, children?: list<array<string, mixed>>}>
     */
    protected function importItems(array $items, int $depth): array
    {
        $result = [];
        $maxDepth = (int) config('verso.menus.max_depth');

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? 'custom';
            $entryId = is_int($item['entry_id'] ?? null) && $type === 'entry' && Entry::query()->whereKey($item['entry_id'])->exists() ? $item['entry_id'] : null;
            $termId = is_int($item['term_id'] ?? null) && $type === 'term' && Term::query()->whereKey($item['term_id'])->exists() ? $item['term_id'] : null;
            $type = $entryId !== null ? 'entry' : ($termId !== null ? 'term' : 'custom');
            $url = is_string($item['url'] ?? null) ? $item['url'] : '';

            if ($type === 'custom' && preg_match('#^(/|https?://|mailto:|tel:|\#)#i', $url) !== 1) {
                continue;
            }

            $result[] = [
                'type' => $type,
                'entry_id' => $entryId,
                'term_id' => $termId,
                'label' => is_string($item['label'] ?? null) ? mb_substr($item['label'], 0, 255) : null,
                'url' => $type === 'custom' ? mb_substr($url, 0, 2048) : null,
                'open_in_new_tab' => ($item['open_in_new_tab'] ?? false) === true,
                'mega' => ($item['mega'] ?? false) === true,
                'rel' => is_array($item['rel'] ?? null) ? array_values(array_filter($item['rel'], is_string(...))) : [],
                'visibility' => is_string($item['visibility'] ?? null) ? $item['visibility'] : null,
                'children' => $depth < $maxDepth && is_array($item['children'] ?? null) ? $this->importItems($item['children'], $depth + 1) : [],
            ];
        }

        return $result;
    }
}
