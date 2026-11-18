<?php

namespace App\Verso\Install;

// The result of one install check, like "PHP 8.4 or newer", with a fix when it fails.
final readonly class Requirement
{
    public function __construct(
        public string $group,
        public string $label,
        public bool $passed,
        public string $detail = '',
        public string $fix = '',
    ) {}

    /**
     * @return array{group: string, label: string, passed: bool, detail: string, fix: string}
     */
    public function toArray(): array
    {
        return ['group' => $this->group, 'label' => $this->label, 'passed' => $this->passed, 'detail' => $this->detail, 'fix' => $this->passed ? '' : $this->fix];
    }
}
