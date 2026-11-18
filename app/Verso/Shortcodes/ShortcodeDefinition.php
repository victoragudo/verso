<?php

namespace App\Verso\Shortcodes;

use App\Verso\I18n\Translate;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Illuminate\Support\Facades\Validator;

// A plugin block seen as a shortcode: same name, same fields, same renderer and same capability.
final readonly class ShortcodeDefinition
{
    protected const array TRUE_WORDS = ['1', 'true', 'yes', 'on'];

    protected const array FALSE_WORDS = ['0', 'false', 'no', 'off', ''];

    public function __construct(public PluginBlock&HasBlockFields $block, public string $plugin = '') {}

    public function name(): string
    {
        return $this->block->name();
    }

    public function label(): string
    {
        return $this->block->label();
    }

    public function enclosing(): bool
    {
        return $this->block instanceof HasChildBlocks;
    }

    public function keyAttribute(): ?string
    {
        return $this->block instanceof EmbeddableBlock ? $this->block->keyAttribute() : null;
    }

    public function capability(): ?string
    {
        return $this->block instanceof EmbeddableBlock ? $this->block->capability() : null;
    }

    /**
     * @return array<string, string>
     */
    public function aliases(): array
    {
        return $this->block instanceof EmbeddableBlock ? $this->block->attributeAliases() : [];
    }

    /**
     * @return list<BlockField>
     */
    public function fields(): array
    {
        return $this->block->fields();
    }

    // The value of the key attr in some shortcode attrs (aliases count too).
    /** @param array<string, mixed> $attrs */
    public function keyOf(array $attrs): ?string
    {
        $key = $this->keyAttribute();

        if ($key === null) {
            return null;
        }

        $value = $this->rename($attrs)[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * Checks shortcode attrs against the block fields and turns them into block attrs.
     *
     * @param  array<string, mixed>  $attrs
     * @return array{attrs: array<string, mixed>, errors: list<string>}
     */
    public function validate(array $attrs): array
    {
        $fields = [];

        foreach ($this->fields() as $field) {
            $fields[$field->name] = $field;
        }

        $attrs = $this->rename($attrs);
        $unknown = array_diff(array_keys($attrs), array_keys($fields));

        if ($unknown !== []) {
            return ['attrs' => [], 'errors' => array_map(fn (string $name): string => Translate::text('Unknown attribute ":name".', ['name' => $name]), array_values($unknown))];
        }

        $values = [];

        foreach ($fields as $name => $field) {
            $values[$name] = array_key_exists($name, $attrs) ? $this->cast($field, $attrs[$name]) : $field->default;
        }

        $validator = Validator::make($values, array_map(fn (BlockField $field): array => $field->rules(), $fields));

        return ['attrs' => $values, 'errors' => array_values($validator->errors()->all())];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    protected function rename(array $attrs): array
    {
        $aliases = $this->aliases();
        $renamed = [];

        foreach ($attrs as $name => $value) {
            $renamed[$aliases[$name] ?? $name] = $value;
        }

        return $renamed;
    }

    // A flag ([form ajax]) is true. Toggle words become booleans. Other values stay text.
    protected function cast(BlockField $field, mixed $value): mixed
    {
        if ($field->type === BlockFieldType::Toggle) {
            $word = is_string($value) ? strtolower(trim($value)) : $value;

            return match (true) {
                $word === true, in_array($word, self::TRUE_WORDS, true) => true,
                in_array($word, self::FALSE_WORDS, true) => false,
                default => $value,
            };
        }

        return $value === true ? '' : $value;
    }
}
