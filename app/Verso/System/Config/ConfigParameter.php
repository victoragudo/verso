<?php

namespace App\Verso\System\Config;

use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;

// One setting the admin may change, declared once with its type, unit, limits and help text.
final readonly class ConfigParameter
{
    public const SETTING_PREFIX = 'system.config.';

    /**
     * @param  string  $key  the config() key, like "verso.media.max_upload_kb"
     * @param  array<string, string>  $options  value => label for choice settings
     * @param  list<ApplyAction>  $apply
     * @param  list<ServerLimit>  $limits
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $label,
        public string $help = '',
        public ParameterType $type = ParameterType::Integer,
        public Unit $unit = Unit::None,
        public int|float|null $min = null,
        public int|float|null $max = null,
        public array $options = [],
        public ?string $env = null,
        public Capability $capability = Capability::ManageSystem,
        public array $apply = [],
        public array $limits = [],
        public ?string $plugin = null,
        public ?string $format = null,
    ) {}

    public const FORMAT_EMAIL = 'email';

    public const FORMAT_HOST = 'host';

    // The Settings key that holds the saved value.
    public function settingKey(): string
    {
        return self::SETTING_PREFIX.$this->key;
    }

    // Turn a value from a form or the database into the right PHP type. Bad values stay as they are.
    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            ParameterType::Integer => is_numeric($value) && (float) $value == (int) $value ? (int) $value : $value,
            ParameterType::Decimal => is_numeric($value) ? (float) $value : $value,
            ParameterType::Boolean => is_bool($value) ? $value : (in_array($value, [1, 0, '1', '0', 'true', 'false'], true) ? in_array($value, [1, '1', 'true'], true) : $value),
            ParameterType::TextList => is_string($value) ? array_values(array_filter(array_map(trim(...), explode(',', $value)), fn (string $item): bool => $item !== '')) : $value,
            default => $value,
        };
    }

    // Why the value cannot be used, or null when it is fine. The value is cast first.
    public function problem(mixed $value): ?string
    {
        $value = $this->cast($value);

        return match ($this->type) {
            ParameterType::Integer => is_int($value) ? $this->rangeProblem($value) : Translate::text('Enter a whole number.'),
            ParameterType::Decimal => is_float($value) ? $this->rangeProblem($value) : Translate::text('Enter a number.'),
            ParameterType::Boolean => is_bool($value) ? null : Translate::text('Choose on or off.'),
            ParameterType::Choice => is_string($value) && array_key_exists($value, $this->options) ? null : Translate::text('Choose one of the listed values.'),
            ParameterType::Text => is_string($value) ? $this->lengthProblem($value) : Translate::text('Enter some text.'),
            ParameterType::TextList => is_array($value) && array_is_list($value) && array_filter($value, fn (mixed $item): bool => ! is_string($item)) === [] ? null : Translate::text('Enter values separated by commas.'),
        };
    }

    // True when the value depends on PHP or web server upload limits.
    public function isUploadLimit(): bool
    {
        return in_array(ServerLimit::UploadMaxFilesize, $this->limits, true);
    }

    protected function rangeProblem(int|float $value): ?string
    {
        if ($this->min !== null && $value < $this->min) {
            return Translate::icu('Use {value} or more.', ['value' => $this->unit->amount($this->min)]);
        }

        if ($this->max !== null && $value > $this->max) {
            return Translate::icu('Use {value} or less.', ['value' => $this->unit->amount($this->max)]);
        }

        return null;
    }

    protected function lengthProblem(string $value): ?string
    {
        if ($this->format === self::FORMAT_EMAIL && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return Translate::text('Enter an email address like news@example.com.');
        }

        if ($this->format === self::FORMAT_HOST && preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $value) !== 1) {
            return Translate::text('Enter a host name like smtp.example.com or an IP address.');
        }

        if ($this->max !== null && mb_strlen($value) > $this->max) {
            return Translate::icu('{count, plural, one {Use # character or fewer.} other {Use # characters or fewer.}}', ['count' => (int) $this->max]);
        }

        if ($this->min !== null && mb_strlen($value) < $this->min) {
            return Translate::icu('{count, plural, one {Use at least # character.} other {Use at least # characters.}}', ['count' => (int) $this->min]);
        }

        return null;
    }

    /**
     * The data the admin page needs, without the current value.
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $plugin = null): array
    {
        return [
            'key' => $this->key,
            'group' => $this->group,
            'label' => $this->translate($this->label, $plugin),
            'help' => $this->translate($this->help, $plugin),
            'type' => $this->type->value,
            'unit' => $this->unit->value,
            'dimension' => $this->unit->dimension(),
            'min' => $this->min,
            'max' => $this->max,
            'options' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $this->translate($label, $plugin)], array_keys($this->options), $this->options),
            'env' => $this->env,
            'apply' => array_map(fn (ApplyAction $action): array => ['key' => $action->value, 'label' => $action->label()], $this->apply),
            'limits' => array_map(fn (ServerLimit $limit): string => $limit->value, $this->limits),
            'plugin' => $this->plugin,
            'format' => $this->format,
        ];
    }

    public function translatedLabel(?string $plugin = null): string
    {
        return $this->translate($this->label, $plugin);
    }

    protected function translate(string $text, ?string $plugin): string
    {
        return $text === '' ? '' : Translate::text($text, plugin: $plugin ?? $this->plugin);
    }
}
