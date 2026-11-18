<?php

namespace App\Verso\System\Server;

// The biggest upload that will really work, and which limit decides it.
final readonly class UploadLimit
{
    /**
     * @param  list<array{key: string, label: string, bytes: int|null, note: string}>  $parts  null bytes means no limit or unknown
     */
    public function __construct(
        public int $bytes,
        public string $bottleneck,
        public array $parts,
    ) {}

    // True when PHP or the web server allows less than the Verso setting.
    public function isLimitedByServer(): bool
    {
        return $this->bottleneck !== UploadLimits::VERSO;
    }

    public function kilobytes(): int
    {
        return intdiv($this->bytes, 1024);
    }

    // One plain sentence about the result.
    public function explanation(): string
    {
        $size = UploadLimits::human($this->bytes);

        foreach ($this->parts as $part) {
            if ($part['key'] === $this->bottleneck) {
                return $this->isLimitedByServer()
                    ? __('Uploads stop at :size because of :limit. Raise it to allow bigger files.', ['size' => $size, 'limit' => $part['label']])
                    : __('Uploads up to :size work. The Verso setting is the lowest limit.', ['size' => $size]);
            }
        }

        return __('Uploads up to :size work.', ['size' => $size]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bytes' => $this->bytes,
            'human' => UploadLimits::human($this->bytes),
            'bottleneck' => $this->bottleneck,
            'limitedByServer' => $this->isLimitedByServer(),
            'explanation' => $this->explanation(),
            'parts' => $this->parts,
        ];
    }
}
