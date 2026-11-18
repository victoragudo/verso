<?php

namespace App\Verso\Editor\Builder;

// A group of blocks in the "Add" panel, like "Text" or "Media".
final readonly class BlockCategory
{
    public function __construct(
        public string $key,
        public string $label,
        public int $order = 50,
    ) {}

    /** @return array{key: string, label: string, order: int} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'order' => $this->order];
    }
}
