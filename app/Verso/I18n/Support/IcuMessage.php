<?php

namespace App\Verso\I18n\Support;

// The PHP side of the admin message format: {name}, {n, number}, {n, plural, ...},
// {n, selectordinal, ...} and {x, select, ...}, with # inside plurals. Same rules as resources/js/lib/i18n/format.ts.
class IcuMessage
{
    protected int $index = 0;

    protected function __construct(protected string $source) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function format(string $message, array $values, string $locale): string
    {
        if (! str_contains($message, '{') && ! str_contains($message, "'")) {
            return $message;
        }

        return self::render((new self($message))->nodes(false, 0), $values, $locale, null);
    }

    /**
     * Argument names a message uses, sorted.
     *
     * @return list<string>
     */
    public static function arguments(string $message): array
    {
        $names = [];
        self::walk((new self($message))->nodes(false, 0), $names);
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  list<string>  $names
     *
     * @param-out list<string> $names
     */
    protected static function walk(array $nodes, array &$names): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (isset($node['name']) && is_string($node['name'])) {
                $names[] = $node['name'];
            }

            foreach ((array) ($node['options'] ?? []) as $branch) {
                if (is_array($branch)) {
                    self::walk($branch, $names);
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function nodes(bool $inPlural, int $depth): array
    {
        $nodes = [];
        $text = '';
        $length = strlen($this->source);

        while ($this->index < $length) {
            $char = $this->source[$this->index];

            if ($char === "'") {
                $text .= $this->quoted($inPlural);

                continue;
            }

            if ($char === '{') {
                if ($text !== '') {
                    $nodes[] = ['kind' => 'text', 'value' => $text];
                    $text = '';
                }

                $nodes[] = $this->argument($depth);

                continue;
            }

            if ($char === '}' && $depth > 0) {
                break;
            }

            if ($char === '#' && $inPlural) {
                if ($text !== '') {
                    $nodes[] = ['kind' => 'text', 'value' => $text];
                    $text = '';
                }

                $nodes[] = ['kind' => 'pound'];
                $this->index++;

                continue;
            }

            $text .= $char;
            $this->index++;
        }

        if ($text !== '') {
            $nodes[] = ['kind' => 'text', 'value' => $text];
        }

        return $nodes;
    }

    protected function quoted(bool $inPlural): string
    {
        $next = $this->source[$this->index + 1] ?? '';

        if ($next === "'") {
            $this->index += 2;

            return "'";
        }

        if ($next === '{' || $next === '}' || ($inPlural && $next === '#')) {
            $end = strpos($this->source, "'", $this->index + 1);
            $stop = $end === false ? strlen($this->source) : $end;
            $value = substr($this->source, $this->index + 1, $stop - $this->index - 1);
            $this->index = $stop + 1;

            return $value;
        }

        $this->index++;

        return "'";
    }

    /**
     * @return array<string, mixed>
     */
    protected function argument(int $depth): array
    {
        $start = $this->index;
        $this->index++;
        $name = $this->word();
        $this->space();

        if (($this->source[$this->index] ?? '') === '}') {
            $this->index++;

            return ['kind' => 'arg', 'name' => $name, 'style' => null];
        }

        if (($this->source[$this->index] ?? '') !== ',') {
            return $this->broken($start);
        }

        $this->index++;
        $this->space();
        $type = $this->word();
        $this->space();

        if (($this->source[$this->index] ?? '') === '}') {
            $this->index++;

            return ['kind' => 'arg', 'name' => $name, 'style' => $type];
        }

        if (($this->source[$this->index] ?? '') !== ',') {
            return $this->broken($start);
        }

        $this->index++;

        if (in_array($type, ['plural', 'selectordinal', 'select'], true)) {
            return $this->options($name, $type, $depth);
        }

        $end = strpos($this->source, '}', $this->index);

        if ($end === false) {
            return $this->broken($start);
        }

        $style = trim(substr($this->source, $this->index, $end - $this->index));
        $this->index = $end + 1;

        return ['kind' => 'arg', 'name' => $name, 'style' => $type.':'.$style];
    }

    /**
     * @return array<string, mixed>
     */
    protected function options(string $name, string $type, int $depth): array
    {
        $options = [];
        $offset = 0;
        $length = strlen($this->source);

        while (true) {
            $this->space();

            if ($this->index >= $length) {
                break;
            }

            if ($this->source[$this->index] === '}') {
                $this->index++;

                break;
            }

            $selector = $this->word();

            if ($selector === '') {
                $this->index++;

                continue;
            }

            if (str_starts_with($selector, 'offset:')) {
                $offset = (int) substr($selector, 7);

                continue;
            }

            $this->space();

            if (($this->source[$this->index] ?? '') !== '{') {
                break;
            }

            $this->index++;
            $options[$selector] = $this->nodes($type !== 'select', $depth + 1);
            $this->index++;
        }

        return ['kind' => $type, 'name' => $name, 'offset' => $offset, 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    protected function broken(int $start): array
    {
        $end = strpos($this->source, '}', $start);
        $stop = $end === false ? strlen($this->source) : $end + 1;
        $this->index = $stop;

        return ['kind' => 'text', 'value' => substr($this->source, $start, $stop - $start)];
    }

    protected function word(): string
    {
        if (preg_match('/\G[^\s{},]+/', $this->source, $match, 0, $this->index) !== 1) {
            return '';
        }

        $this->index += strlen($match[0]);

        return $match[0];
    }

    protected function space(): void
    {
        while (ctype_space($this->source[$this->index] ?? 'x')) {
            $this->index++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $values
     */
    protected static function render(array $nodes, array $values, string $locale, int|float|null $pound): string
    {
        $output = '';

        foreach ($nodes as $node) {
            $output .= match ($node['kind']) {
                'text' => $node['value'],
                'pound' => $pound === null ? '#' : self::number($pound, $locale),
                'arg' => array_key_exists($node['name'], $values) ? self::display($values[$node['name']], $locale) : '{'.$node['name'].'}',
                'select' => self::render($node['options'][(string) ($values[$node['name']] ?? 'other')] ?? $node['options']['other'] ?? [], $values, $locale, $pound),
                default => self::plural($node, $values, $locale),
            };
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $values
     */
    protected static function plural(array $node, array $values, string $locale): string
    {
        $raw = $values[$node['name']] ?? 0;
        $raw = is_numeric($raw) ? $raw + 0 : 0;
        $number = $raw - $node['offset'];
        $category = PluralRules::select($locale, $number, $node['kind'] === 'selectordinal');
        $branch = $node['options']['='.$raw] ?? $node['options'][$category] ?? $node['options']['other'] ?? [];

        return self::render($branch, $values, $locale, $number);
    }

    protected static function display(mixed $value, string $locale): string
    {
        return match (true) {
            is_int($value), is_float($value) => self::number($value, $locale),
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }

    protected static function number(int|float $value, string $locale): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $formatted = (new \NumberFormatter(str_replace('-', '_', $locale), \NumberFormatter::DECIMAL))->format($value);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return is_int($value) ? number_format($value) : rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
