<?php

namespace App\Verso\Layouts\Conditions;

use App\Verso\Editor\Builder\Registry;
use App\Verso\I18n\Translate;

/**
 * Every condition a layout can use. Core registers its own with the same call plugins and themes use.
 *
 * A layout keeps a list of rules: {"type": "term", "value": "category:3", "exclude": false}.
 * It shows when one rule that is not "exclude" matches and no "exclude" rule matches.
 *
 * @extends Registry<LayoutCondition>
 */
class ConditionRegistry extends Registry
{
    public function register(LayoutCondition $condition): void
    {
        $this->put($condition->key(), $condition);
    }

    /**
     * How well a layout fits the page: null when it does not show there, else the weight of its best rule.
     *
     * @param  array<int, mixed>  $rules
     */
    public function score(array $rules, PageContext $page): ?int
    {
        $best = null;

        foreach ($rules as $rule) {
            $condition = is_array($rule) && is_string($rule['type'] ?? null) ? $this->find($rule['type']) : null;

            if ($condition === null) {
                continue;
            }

            $value = is_scalar($rule['value'] ?? null) ? (string) $rule['value'] : '';

            if (! $condition->matches($page, $value)) {
                continue;
            }

            if (($rule['exclude'] ?? false) === true) {
                return null;
            }

            $best = max($best ?? 0, $condition->weight());
        }

        return $best;
    }

    /**
     * Problems with a list of rules, as plain sentences. Empty when they are fine.
     *
     * @param  array<int, mixed>  $rules
     * @return list<string>
     */
    public function errors(array $rules): array
    {
        $errors = [];

        foreach (array_values($rules) as $index => $rule) {
            $condition = is_array($rule) && is_string($rule['type'] ?? null) ? $this->find($rule['type']) : null;

            if ($condition === null) {
                $errors[] = Translate::text('Rule :number: this kind of rule is unknown.', ['number' => $index + 1]);

                continue;
            }

            $options = $condition->options();
            $value = $rule['value'] ?? null;

            if ($options !== null && ! in_array((string) (is_scalar($value) ? $value : ''), array_column($options, 'value'), true)) {
                $errors[] = Translate::text('Rule :number: pick a value from the list.', ['number' => $index + 1]);
            }
        }

        return $errors;
    }

    /** @return list<array{key: string, label: string, options: list<array{value: string, label: string}>|null}> */
    public function toArray(): array
    {
        return array_values(array_map(fn (LayoutCondition $condition): array => [
            'key' => $condition->key(),
            'label' => Translate::text($condition->label()),
            'options' => $condition->options(),
        ], $this->items));
    }
}
