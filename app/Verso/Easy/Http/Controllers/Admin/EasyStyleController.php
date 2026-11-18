<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Easy\Actions\ApplyEasyStyle;
use App\Verso\Easy\Http\Requests\SaveEasyStyleRequest;
use App\Verso\Easy\Starters\StarterSiteRegistry;
use App\Verso\Easy\Styles\FontPair;
use App\Verso\Easy\Styles\StylePreset;
use App\Verso\Easy\Styles\ThemeStyleOptions;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// "Make it yours": logo, colors from the logo, a font pair and a style preset, with a live preview of the real site.
class EasyStyleController extends Controller
{
    public function __construct(
        protected ThemeManager $themes,
        protected ThemeStyleOptions $options,
        protected EasyState $state,
    ) {}

    public function show(Request $request, GlobalStyles $globalStyles, ThemeCustomizer $customizer, StarterSiteRegistry $starters): Response
    {
        $theme = $this->themes->active();
        $values = $globalStyles->values($theme);
        $presets = $this->options->presets($theme);
        $pairs = $this->options->fontPairs($theme);
        $preset = $this->chosenPreset($presets, $starters);
        $fontPair = $this->state->fontPair() ?? $presets[$preset]->fontPair ?? null;
        $logo = $customizer->logo($theme);

        return Inertia::render('admin/easy/style', [
            'theme' => ['name' => $theme->name, 'slug' => $theme->slug],
            'roles' => array_map(
                fn (string $role, array $color): array => ['role' => $role, 'token' => $color['token'], 'label' => $color['label']],
                array_keys($this->options->colorRoles($theme)),
                $this->options->colorRoles($theme),
            ),
            'colors' => array_map(fn (array $color): string => $values[$color['token']], $this->options->colorRoles($theme)),
            'presets' => array_values(array_map(fn (StylePreset $item): array => $this->options->presetForBrowser($theme, $item), $presets)),
            'fontPairs' => array_values(array_map(fn (FontPair $pair): array => $this->fontPair($theme, $pair), $pairs)),
            'fontFaces' => $this->options->fontFaces($theme),
            'preset' => $preset,
            'fontPair' => $fontPair !== null && isset($pairs[$fontPair]) ? $fontPair : null,
            'logo' => $logo === null ? null : ['id' => $logo->id, 'url' => $logo->url(), 'filename' => $logo->filename],
            'logoSupported' => $customizer->logoField($theme) !== null,
            'canUpload' => (bool) $request->user()?->can(Capability::UploadFiles->value),
            'previewUrl' => route('admin.site-editor.preview'),
            'previewWidths' => config('verso.site_parts.preview_widths'),
            'previewDelay' => (int) config('verso.easy.preview_delay'),
            'undoSteps' => (int) config('verso.easy.page_editor.undo_steps'),
            'palette' => [
                'sampleSize' => (int) config('verso.easy.palette.sample_size'),
                'maxColors' => (int) config('verso.easy.palette.max_colors'),
            ],
        ]);
    }

    public function update(SaveEasyStyleRequest $request, ApplyEasyStyle $apply): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 403);

        $result = $apply->handle($this->themes->active(), $request->choice());

        if ($result->rejected !== []) {
            return back()->withErrors(['style' => __('This theme does not accept some of these values. Pick another style or font pair.')]);
        }

        Inertia::flash('toast', ['type' => $result->warnings === [] ? 'success' : 'warning', 'message' => $result->message()]);

        return to_route('admin.dashboard');
    }

    /**
     * The preset to show as picked: the saved one, the one of the chosen starter site, or the first.
     *
     * @param  array<string, StylePreset>  $presets
     */
    protected function chosenPreset(array $presets, StarterSiteRegistry $starters): ?string
    {
        $starter = $this->state->starter();
        $candidates = [$this->state->stylePreset(), $starter === null ? null : $starters->find($starter)?->stylePreset];

        foreach ($candidates as $key) {
            if ($key !== null && isset($presets[$key])) {
                return $key;
            }
        }

        return array_key_first($presets);
    }

    /**
     * A font pair with its fonts' labels and stacks for the sample, and the tokens it sets on this theme.
     *
     * @return array<string, mixed>
     */
    protected function fontPair(Theme $theme, FontPair $pair): array
    {
        $labels = ThemeCustomizer::fontLabels();
        $stacks = $this->options->fontStacks();

        return [
            ...$pair->toArray(),
            'label' => $this->options->fontPairLabel($theme, $pair),
            'headingLabel' => $labels[$pair->heading] ?? $pair->heading,
            'bodyLabel' => $labels[$pair->body] ?? $pair->body,
            'headingStack' => $stacks[$pair->heading] ?? '',
            'bodyStack' => $stacks[$pair->body] ?? '',
            'tokens' => $this->options->fontPairTokens($theme, $pair),
        ];
    }
}
