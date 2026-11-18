<?php

namespace App\Verso\Easy\Actions;

use App\Verso\Easy\Styles\ContrastChecker;
use App\Verso\Easy\Styles\EasyStyleChoice;
use App\Verso\Easy\Styles\EasyStyleResult;
use App\Verso\Easy\Styles\ThemeStyleOptions;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Theme;

// Saves the picks of "Make it yours" on the active theme: global style tokens, the logo and the Easy mode settings.
class ApplyEasyStyle
{
    public function __construct(
        protected ThemeStyleOptions $options,
        protected GlobalStyles $globalStyles,
        protected ThemeCustomizer $customizer,
        protected ContrastChecker $contrast,
        protected EasyState $state,
    ) {}

    public function handle(Theme $theme, EasyStyleChoice $choice): EasyStyleResult
    {
        $rejected = $this->globalStyles->save($theme, $this->tokenValues($theme, $choice));
        $logoField = $this->customizer->logoField($theme);

        if ($choice->setLogo && $logoField !== null) {
            $this->customizer->save($theme, [$logoField => $choice->logo]);
        }

        $presets = $this->options->presets($theme);
        $pairs = $this->options->fontPairs($theme);
        $this->state->rememberStyle(
            $choice->preset !== null && isset($presets[$choice->preset]) ? $choice->preset : null,
            $choice->fontPair !== null && isset($pairs[$choice->fontPair]) ? $choice->fontPair : null,
        );

        return new EasyStyleResult($rejected, $this->contrast->review($this->colors($theme)));
    }

    /**
     * Token values of the picks: preset tokens, then font pair fonts, then palette colors. Only tokens the theme declares.
     *
     * @return array<string, string>
     */
    public function tokenValues(Theme $theme, EasyStyleChoice $choice): array
    {
        $preset = $choice->preset !== null ? $this->options->presets($theme)[$choice->preset] ?? null : null;
        $pair = $choice->fontPair !== null ? $this->options->fontPairs($theme)[$choice->fontPair] ?? null : null;
        $values = [...($preset->tokens ?? []), ...($pair !== null ? $this->options->fontPairTokens($theme, $pair) : [])];

        foreach ($this->options->colorRoles($theme) as $role => $color) {
            if (isset($choice->colors[$role])) {
                $values[$color['token']] = strtolower($choice->colors[$role]);
            }
        }

        return $values;
    }

    /**
     * The saved color of every palette role the theme declares.
     *
     * @return array<string, string>
     */
    protected function colors(Theme $theme): array
    {
        $values = $this->globalStyles->values($theme);

        return array_map(fn (array $color): string => $values[$color['token']], $this->options->colorRoles($theme));
    }
}
