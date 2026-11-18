<?php

namespace App\Verso\I18n;

// One admin language: its code, its own name, its English name and the text direction.
final readonly class Locale
{
    public function __construct(
        public string $code,
        public string $name,
        public string $englishName,
        public string $dir = 'ltr',
    ) {}

    public function isRtl(): bool
    {
        return $this->dir === 'rtl';
    }

    /**
     * @return array{code: string, name: string, englishName: string, dir: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'englishName' => $this->englishName, 'dir' => $this->dir];
    }
}
