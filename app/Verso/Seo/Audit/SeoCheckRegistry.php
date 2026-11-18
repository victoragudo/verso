<?php

namespace App\Verso\Seo\Audit;

use InvalidArgumentException;

// Every check of the SEO overview, in the order they were added. Plugins add theirs with add().
class SeoCheckRegistry
{
    /** @var array<string, SeoCheck> */
    protected array $checks = [];

    public function add(SeoCheck ...$checks): void
    {
        foreach ($checks as $check) {
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,60}$/', $check->key()) !== 1) {
                throw new InvalidArgumentException("The SEO check key [{$check->key()}] must be lowercase words with dashes.");
            }

            $this->checks[$check->key()] = $check;
        }
    }

    public function remove(string $key): void
    {
        unset($this->checks[$key]);
    }

    public function find(string $key): ?SeoCheck
    {
        return $this->checks[$key] ?? null;
    }

    /** @return list<SeoCheck> */
    public function all(): array
    {
        return array_values($this->checks);
    }
}
