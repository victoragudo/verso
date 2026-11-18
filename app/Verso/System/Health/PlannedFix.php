<?php

namespace App\Verso\System\Health;

// One change the PHP user can make by itself: chmod, create a folder or link public/storage.
final readonly class PlannedFix
{
    public const CHMOD = 'chmod';

    public const MKDIR = 'mkdir';

    public const STORAGE_LINK = 'storage_link';

    public function __construct(
        public string $type,
        public string $path,
        public ?int $mode = null,
    ) {}

    // What will happen, in plain words.
    public function description(): string
    {
        return match ($this->type) {
            self::CHMOD => __('Change the mode of :path to :mode', ['path' => $this->path, 'mode' => sprintf('%04o', (int) $this->mode)]),
            self::MKDIR => __('Create the folder :path with mode :mode', ['path' => $this->path, 'mode' => sprintf('%04o', (int) $this->mode)]),
            default => __('Link public/storage to storage/app/public'),
        };
    }

    /**
     * @return array{type: string, path: string, mode: string|null, description: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'path' => $this->path, 'mode' => $this->mode === null ? null : sprintf('%04o', $this->mode), 'description' => $this->description()];
    }
}
