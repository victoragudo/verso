<?php

namespace App\Verso\Easy\Http\Requests;

use App\Verso\Easy\Styles\EasyStyleChoice;
use App\Verso\Easy\Styles\ThemeStyleOptions;
use App\Verso\Media\Models\Media;
use App\Verso\Theme\ThemeManager;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the picks of "Make it yours": six digit colors, a preset and a font pair of the active theme, an image as logo.
class SaveEasyStyleRequest extends FormRequest
{
    protected const COLOR = '/^#[0-9a-f]{6}$/i';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $theme = app(ThemeManager::class)->active();
        $options = app(ThemeStyleOptions::class);

        return [
            'colors' => ['nullable', 'array:'.implode(',', ThemeStyleOptions::COLOR_ROLES)],
            'colors.*' => ['required', 'string', 'regex:'.self::COLOR],
            'preset' => ['nullable', 'string', Rule::in(array_keys($options->presets($theme)))],
            'fontPair' => ['nullable', 'string', Rule::in(array_keys($options->fontPairs($theme)))],
            'logo' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || Media::query()->whereKey((int) $value)->first()?->isImage() !== true) {
                    $fail(__('Pick a picture file, like a PNG or JPG, for your logo.'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colors.*.required' => __('Pick a color.'),
            'colors.*.regex' => __('Use a color code like :example.', ['example' => '#2e5e4e']),
            'colors.array' => __('Pick colors for the accent, background, text, quiet text and panels only.'),
            'preset.in' => __('Pick one of the styles on this page.'),
            'fontPair.in' => __('Pick one of the font pairs on this page.'),
        ];
    }

    public function choice(): EasyStyleChoice
    {
        $colors = $this->validated('colors');
        $logo = $this->validated('logo');

        return new EasyStyleChoice(
            colors: is_array($colors) ? array_map(strval(...), $colors) : [],
            preset: is_string($this->validated('preset')) ? $this->validated('preset') : null,
            fontPair: is_string($this->validated('fontPair')) ? $this->validated('fontPair') : null,
            logo: $logo === null ? null : (int) $logo,
            setLogo: $this->exists('logo'),
        );
    }
}
