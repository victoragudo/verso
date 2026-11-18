<?php

namespace App\Verso\System\Health;

// One result on the Server health page, with a fix Verso can do or a shell command.
final readonly class HealthCheck
{
    /**
     * @param  list<PlannedFix>  $fixes
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $label,
        public HealthStatus $status,
        public string $detail = '',
        public ?string $path = null,
        public ?string $mode = null,
        public ?string $recommended = null,
        public array $fixes = [],
        public ?string $command = null,
    ) {}

    public function isProblem(): bool
    {
        return $this->status === HealthStatus::Warning || $this->status === HealthStatus::Danger;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'group' => $this->group,
            'label' => $this->label,
            'status' => $this->status->value,
            'detail' => $this->detail,
            'path' => $this->path,
            'mode' => $this->mode,
            'recommended' => $this->recommended,
            'fixes' => array_map(fn (PlannedFix $fix): array => $fix->toArray(), $this->fixes),
            'command' => $this->command,
        ];
    }
}
