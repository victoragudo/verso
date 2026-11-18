<?php

namespace Verso\Plugins\Forms\Fields;

use Illuminate\Validation\Rule;
use Verso\Plugins\Forms\Enums\FieldType;

// One field of a form, read from the JSON that the builder saves.
final readonly class FormField
{
    // Widths a field can take in the multi column layout.
    public const WIDTHS = ['full', 'half'];

    /**
     * @param  list<string>  $options
     * @param  list<string>  $accept
     */
    public function __construct(
        public string $id,
        public FieldType $type,
        public string $name,
        public string $label,
        public string $placeholder = '',
        public string $help = '',
        public bool $required = false,
        public string $width = 'full',
        public array $options = [],
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public array $accept = [],
        public ?int $maxKb = null,
        public string $value = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $type = FieldType::tryFrom((string) ($data['type'] ?? ''));
        $name = (string) ($data['name'] ?? '');

        if ($type === null || preg_match('/^[a-z][a-z0-9_]{0,39}$/', $name) !== 1) {
            return null;
        }

        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? max(0, (int) $data[$key]) : null;
        $list = fn (string $key): array => array_values(array_filter(
            array_map(fn ($item): string => trim((string) $item), is_array($data[$key] ?? null) ? $data[$key] : []),
            fn (string $item): bool => $item !== '',
        ));

        return new self(
            id: (string) ($data['id'] ?? $name),
            type: $type,
            name: $name,
            label: trim((string) ($data['label'] ?? '')) ?: $name,
            placeholder: (string) ($data['placeholder'] ?? ''),
            help: (string) ($data['help'] ?? ''),
            required: (bool) ($data['required'] ?? false),
            width: in_array($data['width'] ?? null, self::WIDTHS, true) ? (string) $data['width'] : 'full',
            options: $type->hasOptions() ? $list('options') : [],
            minLength: $type->hasLength() ? $int('min_length') : null,
            maxLength: $type->hasLength() ? $int('max_length') : null,
            accept: $type === FieldType::File ? array_map('strtolower', $list('accept')) : [],
            maxKb: $type === FieldType::File ? $int('max_kb') : null,
            value: (string) ($data['value'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'required' => $this->required,
            'width' => $this->width,
            'options' => $this->options,
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
            'accept' => $this->accept,
            'max_kb' => $this->maxKb,
            'value' => $this->value,
        ];
    }

    // Hidden fields always send the value set in the builder, never what the browser sends.
    public function takesInput(): bool
    {
        return $this->type !== FieldType::Hidden;
    }

    public function textMax(): int
    {
        $limit = $this->type === FieldType::Textarea ? (int) config('forms.textarea_max') : (int) config('forms.text_max');

        return $this->maxLength !== null && $this->maxLength > 0 ? min($this->maxLength, $limit) : $limit;
    }

    public function uploadKb(): int
    {
        $max = (int) config('forms.upload_max_kb');

        return min($max, $this->maxKb !== null && $this->maxKb > 0 ? $this->maxKb : (int) config('forms.upload_default_kb'));
    }

    /**
     * File types this field accepts: its own list, limited to the types the site allows.
     *
     * @return list<string>
     */
    public function uploadTypes(): array
    {
        $allowed = array_map('strtolower', (array) config('forms.upload_types'));

        return array_values($this->accept === [] ? $allowed : array_intersect($this->accept, $allowed));
    }

    // The value for the HTML accept attribute, like ".pdf,.jpg".
    public function acceptAttribute(): string
    {
        return implode(',', array_map(fn (string $type): string => '.'.$type, $this->uploadTypes()));
    }

    /**
     * Laravel rules for the value a visitor sends.
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        $presence = $this->required ? 'required' : 'nullable';

        return match ($this->type) {
            FieldType::Text, FieldType::Textarea => array_values(array_filter([
                $presence, 'string',
                $this->minLength ? 'min:'.$this->minLength : null,
                'max:'.$this->textMax(),
            ])),
            FieldType::Email => [$presence, 'string', 'email:rfc', 'max:190'],
            FieldType::Phone => [$presence, 'string', 'max:40', 'regex:/^\+?[0-9 ().\/-]{5,40}$/'],
            FieldType::Select, FieldType::Radio => [$presence, 'string', Rule::in($this->options)],
            FieldType::Checkboxes => [$this->required ? 'required' : 'nullable', 'array', $this->required ? 'min:1' : 'min:0'],
            FieldType::Date => [$presence, 'date_format:Y-m-d'],
            FieldType::File => [$presence, 'file', 'max:'.$this->uploadKb(), 'extensions:'.implode(',', $this->uploadTypes()), 'mimes:'.implode(',', $this->uploadTypes())],
            FieldType::Consent => $this->required ? ['accepted'] : ['nullable', 'boolean'],
            FieldType::Hidden => ['nullable'],
        };
    }

    /**
     * Rules for each item of a list value (checkboxes).
     *
     * @return list<mixed>|null
     */
    public function itemRules(): ?array
    {
        return $this->type === FieldType::Checkboxes ? ['string', Rule::in($this->options)] : null;
    }

    // Autocomplete hint for the browser, so autofill works.
    public function autocomplete(): ?string
    {
        return match (true) {
            $this->type === FieldType::Email => 'email',
            $this->type === FieldType::Phone => 'tel',
            $this->type === FieldType::Text && in_array($this->name, ['name', 'full_name', 'your_name'], true) => 'name',
            $this->type === FieldType::Text && in_array($this->name, ['company', 'organization'], true) => 'organization',
            default => null,
        };
    }
}
