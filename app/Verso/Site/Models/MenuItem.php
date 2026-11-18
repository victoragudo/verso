<?php

namespace App\Verso\Site\Models;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Enums\MenuItemVisibility;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One link in a menu: an entry, a term or a custom URL.
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property MenuItemType $type
 * @property int|null $entry_id
 * @property int|null $term_id
 * @property string|null $label
 * @property string|null $url
 * @property bool $open_in_new_tab
 * @property bool $mega
 * @property string|null $rel
 * @property MenuItemVisibility $visibility
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Menu $menu
 * @property-read MenuItem|null $parent
 * @property-read Collection<int, MenuItem> $children
 * @property-read Entry|null $entry
 * @property-read Term|null $term
 */
#[Fillable(['menu_id', 'parent_id', 'type', 'entry_id', 'term_id', 'label', 'url', 'open_in_new_tab', 'position', 'mega', 'rel', 'visibility'])]
#[UseFactory(MenuItemFactory::class)]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'open_in_new_tab' => false,
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'open_in_new_tab' => 'boolean',
            'position' => 'integer',
            'mega' => 'boolean',
            'visibility' => MenuItemVisibility::class,
        ];
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    // The text to show: the custom label, or the title of the linked entry or term.
    public function resolvedLabel(): string
    {
        $label = trim((string) $this->label);

        if ($label !== '') {
            return $label;
        }

        return match ($this->type) {
            MenuItemType::Entry => $this->entry->title ?? '',
            MenuItemType::Term => $this->term->name ?? '',
            MenuItemType::Custom => (string) $this->url,
        };
    }

    // The link, or null when the target is gone or not public.
    public function resolvedUrl(): ?string
    {
        return match ($this->type) {
            MenuItemType::Entry => $this->entry !== null && $this->entry->isPublished() ? $this->entry->url() : null,
            MenuItemType::Term => $this->term?->url(),
            MenuItemType::Custom => $this->customUrl(),
        };
    }

    // Visitors only see items whose target is public.
    public function isVisible(): bool
    {
        return $this->resolvedUrl() !== null;
    }

    // Local paths like "/contact" become full URLs. Other links are kept as they are.
    protected function customUrl(): ?string
    {
        $url = trim((string) $this->url);

        if ($url === '') {
            return null;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }
}
