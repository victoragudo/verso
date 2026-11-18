<?php

namespace App\Verso\Layouts\Conditions;

use App\Models\User;
use App\Verso\Access\Roles\Roles;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\I18n\Translate;
use Closure;

// The conditions of core: everywhere, kind of page, content type, one entry, a term, an author, signed in and role.
final class CoreConditions
{
    // Kinds of public pages people can pick.
    public const array PAGE_KINDS = [
        'entry' => ['label' => 'Single entries'],
        'front-page' => ['label' => 'Front page'],
        'home' => ['label' => 'Latest posts on the home page'],
        'archive' => ['label' => 'Content type archives'],
        'taxonomy' => ['label' => 'Category and tag pages'],
        'author' => ['label' => 'Author pages'],
        'search' => ['label' => 'Search results'],
        '404' => ['label' => 'Page not found'],
    ];

    // Most entries and terms listed as choices.
    public const int MAX_CHOICES = 500;

    public static function register(ConditionRegistry $registry): void
    {
        $registry->register(self::make(key: 'everywhere', label: 'Everywhere', weight: 0, options: null, matches: fn (): bool => true));
        $registry->register(self::make(key: 'page_kind', label: 'Kind of page', weight: 10, options: fn (): array => array_map(
            fn (string|int $value, array $kind): array => ['value' => (string) $value, 'label' => Translate::text($kind['label'])],
            array_keys(self::PAGE_KINDS),
            self::PAGE_KINDS,
        ), matches: fn (PageContext $page, string $value): bool => $page->kind === $value || ($value === 'entry' && $page->kind === 'front-page')));
        $registry->register(self::make(key: 'content_type', label: 'Content type', weight: 30, options: fn (): array => array_values(array_map(
            fn ($type): array => ['value' => $type->name, 'label' => Translate::text($type->pluralLabel)],
            app(ContentTypeRegistry::class)->all(),
        )), matches: fn (PageContext $page, string $value): bool => $page->type() === $value));
        $registry->register(self::make(key: 'entry', label: 'One entry or page', weight: 100, options: fn (): array => array_values(Entry::query()->published()->orderBy('type')->orderBy('title')->limit(self::MAX_CHOICES)->get(['id', 'title', 'type'])
            ->map(fn (Entry $entry): array => ['value' => (string) $entry->id, 'label' => $entry->title.' ('.$entry->type.')'])->values()->all()),
            matches: fn (PageContext $page, string $value): bool => $page->entry !== null && (string) $page->entry->id === $value));
        $registry->register(self::make(key: 'term', label: 'Category or tag', weight: 60, options: fn (): array => array_values(Term::query()->orderBy('taxonomy')->orderBy('name')->limit(self::MAX_CHOICES)->get(['id', 'name', 'taxonomy'])
            ->map(fn (Term $term): array => ['value' => (string) $term->id, 'label' => $term->name.' ('.Translate::text(app(TaxonomyRegistry::class)->find($term->taxonomy)->label ?? $term->taxonomy).')'])->values()->all()),
            matches: fn (PageContext $page, string $value): bool => ($page->term !== null && (string) $page->term->id === $value)
                || ($page->entry !== null && $page->entry->terms->contains(fn (Term $term): bool => (string) $term->id === $value))));
        $registry->register(self::make(key: 'author', label: 'Author', weight: 50, options: fn (): array => array_values(User::query()->whereIn('id', Entry::query()->select('author_id'))->orderBy('name')->limit(self::MAX_CHOICES)->get(['id', 'name'])
            ->map(fn (User $user): array => ['value' => (string) $user->id, 'label' => $user->name])->values()->all()),
            matches: fn (PageContext $page, string $value): bool => $page->author !== null && (string) $page->author->id === $value));
        $registry->register(self::make(key: 'visitor', label: 'Visitor', weight: 5, options: fn (): array => [
            ['value' => 'guest', 'label' => Translate::text('Not signed in')],
            ['value' => 'signed_in', 'label' => Translate::text('Signed in')],
        ], matches: fn (PageContext $page, string $value): bool => ($value === 'signed_in') === ($page->viewer !== null)));
        $registry->register(self::make(key: 'role', label: 'Role of the visitor', weight: 20, options: fn (): array => array_values(array_map(
            fn ($role): array => ['value' => $role->slug, 'label' => Translate::text($role->name)],
            app(Roles::class)->all(),
        )), matches: fn (PageContext $page, string $value): bool => $page->viewer !== null && $page->viewer->hasRole($value)));
    }

    /**
     * @param  (Closure(): list<array{value: string, label: string}>)|null  $options
     * @param  Closure(PageContext, string): bool  $matches
     */
    public static function make(string $key, string $label, int $weight, ?Closure $options, Closure $matches): LayoutCondition
    {
        return new class($key, $label, $weight, $options, $matches) implements LayoutCondition
        {
            public function __construct(
                protected string $key,
                protected string $label,
                protected int $weight,
                protected ?Closure $options,
                protected Closure $matches,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function label(): string
            {
                return $this->label;
            }

            public function weight(): int
            {
                return $this->weight;
            }

            public function options(): ?array
            {
                return $this->options === null ? null : ($this->options)();
            }

            public function matches(PageContext $page, string $value): bool
            {
                return ($this->matches)($page, $value);
            }
        };
    }
}
