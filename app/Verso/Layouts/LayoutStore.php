<?php

namespace App\Verso\Layouts;

use App\Verso\Layouts\Models\SiteLayout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Saves, publishes and reads theme builder layouts. Published layouts are read once per request.
class LayoutStore
{
    /** @var list<SiteLayout>|null */
    protected ?array $published = null;

    /** @var array<int, SiteLayout> layouts shown with unsaved changes in this request, for the editor preview */
    protected array $previews = [];

    public function __construct(protected int $maxRevisions) {}

    /**
     * Live layouts of one kind, previews first.
     *
     * @return list<SiteLayout>
     */
    public function live(LayoutKind $kind): array
    {
        if ($this->published === null) {
            $this->published = array_values(SiteLayout::query()->whereNotNull('published_blocks')->where('status', '!=', SiteLayout::OFF)->get()->all());
        }
        $previews = array_values(array_filter($this->previews, fn (SiteLayout $layout): bool => $layout->kind === $kind));
        $ids = array_map(fn (SiteLayout $layout): int => $layout->id, $previews);

        return [...$previews, ...array_values(array_filter($this->published, fn (SiteLayout $layout): bool => $layout->kind === $kind && ! in_array($layout->id, $ids, true)))];
    }

    /**
     * Shows a layout with these blocks and settings in this request only. It wins over every other layout of its kind.
     *
     * @param  array<int, mixed>  $blocks
     * @param  array<string, mixed>  $settings
     */
    public function preview(SiteLayout $layout, array $blocks, array $settings): void
    {
        $copy = $layout->replicate();
        $copy->id = $layout->id;
        $copy->published_blocks = $blocks;
        $copy->published_settings = LayoutSettings::clean($layout->kind, $settings);
        $copy->priority = PHP_INT_MAX;
        $copy->status = SiteLayout::PUBLISHED;
        $copy->conditions = [['type' => 'everywhere']];
        $this->previews[$layout->id] = $copy;
    }

    public function isPreview(SiteLayout $layout): bool
    {
        return isset($this->previews[$layout->id]);
    }

    /**
     * Saves the working copy. Visitors still see the published version.
     *
     * @param  array<string, mixed>  $data  blocks, conditions, settings, name, priority
     */
    public function saveDraft(SiteLayout $layout, array $data, ?int $userId): SiteLayout
    {
        $layout->fill([
            ...$this->fields($layout, $data),
            'status' => $layout->status === SiteLayout::OFF ? SiteLayout::OFF : SiteLayout::DRAFT,
            'user_id' => $userId,
        ])->save();

        return $layout;
    }

    /**
     * Makes the working copy live, keeps a revision and tells the caches.
     *
     * @param  array<string, mixed>  $data
     */
    public function publish(SiteLayout $layout, array $data, ?int $userId): SiteLayout
    {
        DB::transaction(function () use ($layout, $data, $userId): void {
            $fields = $this->fields($layout, $data);
            $layout->fill([
                ...$fields,
                'published_blocks' => $fields['blocks'],
                'published_settings' => $fields['settings'],
                'status' => SiteLayout::PUBLISHED,
                'user_id' => $userId,
                'published_at' => Carbon::now(),
            ])->save();

            $layout->revisions()->create(['blocks' => $fields['blocks'], 'conditions' => $fields['conditions'], 'settings' => $fields['settings'], 'user_id' => $userId]);
            $keep = $layout->revisions()->limit(max(1, $this->maxRevisions))->pluck('id');
            $layout->revisions()->whereNotIn('id', $keep)->delete();
        });

        $this->changed($layout);

        return $layout;
    }

    // Keeps the layout but hides it from visitors, or shows it again.
    public function switch(SiteLayout $layout, bool $on): void
    {
        $layout->forceFill(['status' => $on ? ($layout->published_blocks === null ? SiteLayout::DRAFT : SiteLayout::PUBLISHED) : SiteLayout::OFF])->save();
        $this->changed($layout);
    }

    public function delete(SiteLayout $layout): void
    {
        $layout->delete();
        $this->changed($layout);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{blocks: array<int, mixed>, conditions: array<int, mixed>, settings: array<string, mixed>, name: string, priority: int}
     */
    protected function fields(SiteLayout $layout, array $data): array
    {
        return [
            'blocks' => array_values((array) ($data['blocks'] ?? $layout->blocks)),
            'conditions' => array_values(array_map(fn (array $rule): array => [
                'type' => (string) ($rule['type'] ?? ''),
                'value' => is_scalar($rule['value'] ?? null) ? (string) $rule['value'] : '',
                'exclude' => ($rule['exclude'] ?? false) === true,
            ], array_filter((array) ($data['conditions'] ?? $layout->conditions), 'is_array'))),
            'settings' => LayoutSettings::clean($layout->kind, (array) ($data['settings'] ?? $layout->settings)),
            'name' => (string) ($data['name'] ?? $layout->name),
            'priority' => (int) ($data['priority'] ?? $layout->priority),
        ];
    }

    protected function changed(SiteLayout $layout): void
    {
        $this->published = null;
        LayoutPublished::dispatch($layout->id, $layout->kind);
    }
}
