<?php

namespace App\Verso\Plugins\Blocks;

use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Editor\Icons\IconLibrary;
use App\Verso\Editor\Rules\SafeUrl;
use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Enums\BlockFieldType;
use Closure;
use InvalidArgumentException;

// One attr of a plugin block: how the editor shows it and how the server checks it.
final readonly class BlockField
{
    /**
     * @param  array<array-key, string>  $options  value => label, only for select fields
     */
    public function __construct(
        public string $name,
        public string $label,
        public BlockFieldType $type = BlockFieldType::Text,
        public mixed $default = null,
        public array $options = [],
        public string $help = '',
        public bool $required = false,
        public int|float|null $min = null,
        public int|float|null $max = null,
        // Select fields only: always show a search box, even with few options.
        public bool $searchable = false,
        // Length fields only: the units, keywords and numbers the value accepts. Every length unit when null.
        public ?CssValueRule $value = null,
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("The block field name \"{$name}\" must start with a letter and use only letters, numbers and _.");
        }

        if ($type === BlockFieldType::Select && $options === []) {
            throw new InvalidArgumentException("The select field \"{$name}\" needs at least one option.");
        }
    }

    /**
     * Laravel validation rules for this attr.
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];
        $textMax = (int) config('verso.editor.plugin_text_max');

        return [...$rules, ...match ($this->type) {
            BlockFieldType::Text, BlockFieldType::Textarea => ['string', 'max:'.($this->max !== null ? (int) $this->max : $textMax)],
            BlockFieldType::Url => ['string', 'max:2048', new SafeUrl],
            BlockFieldType::Toggle => ['boolean'],
            BlockFieldType::Select => ['string', 'in:'.implode(',', array_map('strval', array_keys($this->options)))],
            BlockFieldType::Number => array_values(array_filter([
                'numeric',
                $this->min !== null ? 'min:'.$this->min : null,
                $this->max !== null ? 'max:'.$this->max : null,
            ])),
            BlockFieldType::Media => ['array', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value) || ! is_int($value['id'] ?? null) || ! is_string($value['url'] ?? null) || app(UrlSanitizer::class)->clean($value['url']) === null) {
                    $fail(Translate::text(':field: pick a picture from the media library.', ['field' => $this->label]));
                }
            }],
            BlockFieldType::Icon => ['string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(IconLibrary::class)->has((string) $value)) {
                    $fail(Translate::text(':field: pick an icon from the list.', ['field' => $this->label]));
                }
            }],
            BlockFieldType::Color => ['string', 'regex:/^(#[0-9a-fA-F]{3,8}|var:color\.[a-z0-9][a-z0-9-]{0,40})$/'],
            BlockFieldType::DateTime => ['string', 'date_format:Y-m-d\TH:i'],
            BlockFieldType::Length => [function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->css($value) === null) {
                    $fail("{$this->label}: use a CSS size like 16px, 1.5rem, 50% or clamp(1rem, 2vw, 2rem).");
                }
            }],
        }];
    }

    // The units, keywords and numbers a length field accepts.
    public function rule(): CssValueRule
    {
        return $this->value ?? new CssValueRule;
    }

    // The clean CSS value of a length field, or null when it is not allowed. Old plain numbers are pixels.
    public function css(mixed $value): ?string
    {
        return $this->type === BlockFieldType::Length ? (new CssValueParser)->normalize($value, $this->rule()) : null;
    }

    /**
     * Shape sent to the editor.
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $plugin = null): array
    {
        return [
            'name' => $this->name,
            'label' => $this->text($this->label, $plugin),
            'type' => $this->type->value,
            'default' => $this->default,
            'options' => array_map(
                fn (string|int $value, string $label): array => ['value' => (string) $value, 'label' => $this->text($label, $plugin)],
                array_keys($this->options),
                array_values($this->options),
            ),
            'help' => $this->text($this->help, $plugin),
            'required' => $this->required,
            'min' => $this->min,
            'max' => $this->max,
            'searchable' => $this->searchable,
            'value' => $this->type === BlockFieldType::Length ? $this->rule()->toArray() : null,
        ];
    }

    protected function text(string $text, ?string $plugin): string
    {
        return $plugin === null || $plugin === '' || $text === '' ? $text : Translate::text($text, plugin: $plugin);
    }
}
