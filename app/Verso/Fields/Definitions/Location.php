<?php

namespace App\Verso\Fields\Definitions;

// Where a field group shows: it matches when every rule of at least one group of rules holds.
final readonly class Location
{
    /**
     * @param  list<list<LocationRule>>  $groups
     */
    public function __construct(public array $groups) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $groups = [];

        foreach ($data as $group) {
            if (! is_array($group)) {
                continue;
            }

            $rules = [];
            foreach ($group as $rule) {
                if (is_array($rule)) {
                    $rules[] = LocationRule::fromArray($rule);
                }
            }

            if ($rules !== []) {
                $groups[] = $rules;
            }
        }

        return new self($groups);
    }

    // Matches when all rules of one group hold. Unknown values count as holding, so callers can check later.
    public function matches(LocationContext $context): bool
    {
        foreach ($this->groups as $rules) {
            $holds = true;

            foreach ($rules as $rule) {
                if ($rule->matches($context) === false) {
                    $holds = false;
                    break;
                }
            }

            if ($holds) {
                return true;
            }
        }

        return false;
    }

    /** @return list<list<array{param: string, operator: string, value: string}>> */
    public function toArray(): array
    {
        return array_map(fn (array $rules): array => array_map(fn (LocationRule $rule): array => $rule->toArray(), $rules), $this->groups);
    }
}
