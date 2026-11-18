<?php

namespace App\Verso\Shortcodes\Usage;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Shortcodes\Enums\UsageKind;
use App\Verso\Shortcodes\Models\EmbedUsage;
use App\Verso\Shortcodes\Parsing\ShortcodeNode;
use App\Verso\Shortcodes\ShortcodeExpander;
use App\Verso\Shortcodes\ShortcodeRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

// Records which entries use which plugin blocks and shortcodes. Unknown shortcodes are kept too, to find orphans.
class UsageIndex
{
    // Usage name of style presets.
    public const string PRESET = 'style-preset';

    public function __construct(
        protected PluginBlockRegistry $blocks,
        protected ShortcodeRegistry $shortcodes,
        protected ShortcodeExpander $expander,
    ) {}

    public function refresh(Entry $entry): void
    {
        $rows = $this->collect($entry);

        DB::transaction(function () use ($entry, $rows): void {
            EmbedUsage::query()->where('entry_id', $entry->id)->delete();

            if ($rows !== []) {
                $now = now();
                EmbedUsage::query()->insert(array_map(fn (array $row): array => [
                    ...$row,
                    'entry_id' => $entry->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $rows));
            }
        });
    }

    /**
     * Distinct usages of one entry: plugin blocks and shortcodes in text blocks and the excerpt.
     *
     * @return list<array{kind: string, name: string, key: ?string}>
     */
    public function collect(Entry $entry): array
    {
        $rows = $this->fromBlocks((array) ($entry->blocks ?? []));

        foreach ($this->expander->parser()->all((string) $entry->excerpt) as $node) {
            $rows[] = $this->shortcodeRow($node);
        }

        $unique = [];

        foreach ($rows as $row) {
            $unique[$row['kind'].'|'.$row['name'].'|'.$row['key']] = $row;
        }

        return array_values($unique);
    }

    /**
     * Entries that embed an item. A null key also matches usages without a key.
     *
     * @param  list<string>  $keys
     * @return Collection<int, Entry>
     */
    public function entriesUsing(string $name, array $keys = []): Collection
    {
        $ids = EmbedUsage::query()
            ->where('name', $name)
            ->when($keys !== [], fn ($query) => $query->where(fn ($inner) => $inner->whereIn('key', $keys)->orWhereNull('key')))
            ->distinct()
            ->pluck('entry_id');

        return Entry::query()->whereIn('id', $ids)->orderBy('title')->get();
    }

    /**
     * Short rows for "Used in" lists in the admin.
     *
     * @param  list<string>  $keys
     * @return list<array{id: int, title: string, type: string, status: string, edit_url: string, url: string}>
     */
    public function usedIn(string $name, array $keys, int $limit): array
    {
        $ids = EmbedUsage::query()->where('name', $name)->whereIn('key', $keys)->distinct()->pluck('entry_id');

        return array_values(Entry::query()->whereIn('id', $ids)->orderBy('title')->limit($limit)->get()
            ->map(fn (Entry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'type' => $entry->type,
                'status' => $entry->status->value,
                'edit_url' => route('admin.entries.edit', ['contentType' => $entry->type, 'entry' => $entry->id]),
                'url' => $entry->url(),
            ])->all());
    }

    /**
     * @param  array<mixed>  $blocks
     * @return list<array{kind: string, name: string, key: ?string}>
     */
    protected function fromBlocks(array $blocks): array
    {
        $rows = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $plugin = $this->blocks->find($block['type']);
            $preset = $block['attrs'][BlockStyleSchema::PRESET_ATTR] ?? null;

            if (is_string($preset) && $preset !== '') {
                $rows[] = ['kind' => UsageKind::Style->value, 'name' => self::PRESET, 'key' => mb_substr($preset, 0, 190)];
            }

            if ($plugin !== null) {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $keyAttribute = $plugin instanceof EmbeddableBlock ? $plugin->keyAttribute() : null;
                $key = $keyAttribute !== null && is_scalar($attrs[$keyAttribute] ?? null) ? (string) $attrs[$keyAttribute] : null;
                $rows[] = ['kind' => UsageKind::Block->value, 'name' => $block['type'], 'key' => $key];
            }

            if (is_string($block['content'] ?? null) && str_contains($block['content'], '[')) {
                foreach ($this->expander->parser()->all($block['content']) as $node) {
                    $rows[] = $this->shortcodeRow($node);
                }
            }

            if (is_array($block['children'] ?? null)) {
                $rows = [...$rows, ...$this->fromBlocks($block['children'])];
            }
        }

        return $rows;
    }

    /**
     * @return array{kind: string, name: string, key: ?string}
     */
    protected function shortcodeRow(ShortcodeNode $node): array
    {
        $key = $this->shortcodes->find($node->name)?->keyOf($node->attrs);

        return ['kind' => UsageKind::Shortcode->value, 'name' => mb_substr($node->name, 0, 100), 'key' => $key === null ? null : mb_substr($key, 0, 190)];
    }
}
