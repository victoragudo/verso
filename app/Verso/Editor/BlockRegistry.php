<?php

namespace App\Verso\Editor;

use App\Verso\Editor\Contracts\Block;
use InvalidArgumentException;

// Keeps every block type. Core registers its blocks. Plugins can add more.
class BlockRegistry
{
    /** @var array<string, Block> */
    protected array $blocks = [];

    // Adds a block type. A block with the same name is replaced.
    public function register(Block $block): void
    {
        $this->blocks[$block->name()] = $block;
    }

    public function has(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    public function get(string $name): Block
    {
        return $this->blocks[$name] ?? throw new InvalidArgumentException("Unknown block type [{$name}].");
    }

    public function find(string $name): ?Block
    {
        return $this->blocks[$name] ?? null;
    }

    /**
     * @return array<string, Block>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->blocks);
    }
}
