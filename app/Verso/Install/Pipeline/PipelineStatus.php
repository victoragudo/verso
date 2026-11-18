<?php

namespace App\Verso\Install\Pipeline;

// Where the install pipeline stands: each step with its status, and the error when one failed.
final readonly class PipelineStatus
{
    /**
     * @param  list<array{key: string, label: string, status: string}>  $steps
     * @param  array{step: string, message: string}|null  $error
     */
    public function __construct(
        public array $steps,
        public ?array $error = null,
    ) {}

    public function finished(): bool
    {
        foreach ($this->steps as $step) {
            if (! in_array($step['status'], [InstallProgress::DONE, InstallProgress::SKIPPED], true)) {
                return false;
            }
        }

        return $this->steps !== [];
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return array{steps: list<array{key: string, label: string, status: string}>, error: array{step: string, message: string}|null, finished: bool}
     */
    public function toArray(): array
    {
        return ['steps' => $this->steps, 'error' => $this->error, 'finished' => $this->finished()];
    }
}
