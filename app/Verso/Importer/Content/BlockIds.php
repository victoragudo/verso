<?php

namespace App\Verso\Importer\Content;

// Makes stable block ids, so importing the same post twice gives the same blocks.
class BlockIds
{
    protected const LENGTH = 6;

    protected int $counter = 0;

    public function __construct(protected string $seed) {}

    public function next(): string
    {
        return 'b_'.substr(hash('xxh3', $this->seed.':'.$this->counter++), 0, self::LENGTH);
    }
}
