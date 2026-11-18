<?php

namespace App\Verso\Layouts;

// The options of each kind of layout, with their defaults. Unknown keys and values are dropped.
final class LayoutSettings
{
    // Option => [allowed values or "bool" / "int:min:max", default].
    public const array OPTIONS = [
        'template' => [
            'width' => [['full', 'wide', 'content'], 'wide'],
        ],
        'header' => [
            'sticky' => ['bool', false],
            'transparent' => ['bool', false],
            'scrollEffect' => [['none', 'solid', 'shrink', 'hide'], 'none'],
            'scrollOffset' => ['int:0:2000', 80],
        ],
        'footer' => [
            'sticky' => ['bool', false],
        ],
        'popup' => [
            'type' => [['modal', 'slide-in', 'top-bar', 'cookie'], 'modal'],
            'position' => [['center', 'start', 'end', 'bottom-start', 'bottom-end', 'bottom', 'top'], 'center'],
            'size' => [['small', 'medium', 'large'], 'medium'],
            'title' => ['text:120', ''],
            'trigger' => [['load', 'delay', 'scroll', 'exit', 'click'], 'delay'],
            'delay' => ['int:0:600', 5],
            'scroll' => ['int:1:100', 50],
            'frequency' => [['always', 'session', 'days', 'dismissed'], 'days'],
            'days' => ['int:1:365', 7],
            'devices' => ['devices', ['computer', 'tablet', 'phone']],
            'dismissLabel' => ['text:60', ''],
            'acceptLabel' => ['text:60', ''],
        ],
    ];

    // Screen sizes a popup can show on.
    public const array DEVICES = ['computer', 'tablet', 'phone'];

    /**
     * The options of a kind, cleaned, with defaults for missing ones.
     *
     * @param  array<mixed>  $settings
     * @return array<string, mixed>
     */
    public static function clean(LayoutKind $kind, array $settings): array
    {
        $clean = [];

        foreach (self::OPTIONS[$kind->value] as $name => [$rule, $default]) {
            $clean[$name] = self::value($rule, $settings[$name] ?? null, $default);
        }

        return $clean;
    }

    /** @param list<string>|string $rule */
    protected static function value(array|string $rule, mixed $value, mixed $default): mixed
    {
        if (is_array($rule)) {
            return is_string($value) && in_array($value, $rule, true) ? $value : $default;
        }

        if ($rule === 'bool') {
            return is_bool($value) ? $value : $default;
        }

        if ($rule === 'devices') {
            return is_array($value) ? array_values(array_intersect(self::DEVICES, $value)) : $default;
        }

        [$type, $min, $max] = array_pad(explode(':', $rule), 3, null);

        if ($type === 'text') {
            return is_string($value) ? mb_substr(trim($value), 0, (int) $min) : $default;
        }

        return is_numeric($value) ? max((int) $min, min((int) $max, (int) $value)) : $default;
    }
}
