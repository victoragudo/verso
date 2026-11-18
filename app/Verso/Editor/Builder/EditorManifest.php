<?php

namespace App\Verso\Editor\Builder;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\Assets\BlockAssetRegistry;
use App\Verso\Editor\Styles\StylePresets;
use App\Verso\Editor\Support\ElementPolicy;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\PluginManager;
use App\Verso\Theme\ThemeManager;
use Closure;

// Everything the client editor needs to know about the builder, sent as the "editor" prop.
class EditorManifest
{
    /**
     * @param  array<string, int>  $breakpoints  device => largest screen width in pixels
     */
    public function __construct(
        protected BlockCategoryRegistry $categories,
        protected StylePropertyRegistry $styles,
        protected EditorAccess $access,
        protected ThemeEditorLimits $theme,
        protected array $breakpoints,
        protected int $recentLimit = 6,
        protected int $undoSteps = 100,
        protected int $undoMergeMs = 800,
        protected int $customCssMax = 2000,
    ) {}

    /**
     * Every key the editor reads; modules add more with extend().
     *
     * @return array<string, mixed>
     */
    public function for(?User $user, ?string $type = null): array
    {
        $breakpoints = [['key' => 'base', 'maxWidth' => null]];

        foreach ($this->breakpoints as $key => $width) {
            $breakpoints[] = ['key' => $key, 'maxWidth' => $width];
        }

        $limits = $this->theme->toArray();
        unset($limits['blocks']);

        return [
            'version' => Registry::CONTRACT_VERSION,
            'categories' => $this->translated($this->categories->toArray()),
            'styleProperties' => $this->translated($this->styles->toArray()),
            'breakpoints' => $breakpoints,
            'features' => $this->access->enabledFeatures($user, $type),
            'allowedBlocks' => $this->access->allowedBlocks($type),
            'recentLimit' => $this->recentLimit,
            'undoSteps' => $this->undoSteps,
            'undoMergeMs' => $this->undoMergeMs,
            'customCssMax' => $this->customCssMax,
            'elementTags' => app(ElementPolicy::class)->tagOptions(),
            'modules' => $this->modules(),
            'limits' => $limits,
            'stylePresets' => app(StylePresets::class)->toArray(),
            'canEditPresets' => $user?->can(Capability::EditStyles->value) ?? false,
            'iconsUrl' => app(BlockAssetRegistry::class)->url('icon-set', 'json'),
            ...$this->extensions($user, $type),
        ];
    }

    /** @var list<Closure(?User, ?string): array<string, mixed>> */
    protected static array $extensions = [];

    // Lets a module add keys to the manifest, like the ready sections of Easy mode.
    /** @param Closure(?User, ?string): array<string, mixed> $extension */
    public static function extend(Closure $extension): void
    {
        self::$extensions[] = $extension;
    }

    /** @return array<string, mixed> */
    protected function extensions(?User $user, ?string $type): array
    {
        $extra = [];

        foreach (self::$extensions as $extension) {
            $extra = [...$extra, ...$extension($user, $type)];
        }

        return $extra;
    }

    /**
     * The same items with their labels in the admin language.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function translated(array $items): array
    {
        return array_map(fn (array $item): array => ['label' => Translate::text((string) $item['label'])] + $item, $items);
    }

    /**
     * Folders whose client builder modules may run: enabled plugins and the active theme with its parents.
     *
     * @return list<string> like ["plugins/builder-example", "themes/ashgrove"]
     */
    protected function modules(): array
    {
        $modules = array_map(fn (string $slug): string => 'plugins/'.$slug, app(PluginManager::class)->enabledSlugs());
        $themes = app(ThemeManager::class);
        $theme = $themes->active();

        while ($theme !== null && ! in_array('themes/'.$theme->slug, $modules, true)) {
            $modules[] = 'themes/'.$theme->slug;
            $theme = $theme->parent === null ? null : $themes->find($theme->parent);
        }

        return array_values(array_unique($modules));
    }
}
