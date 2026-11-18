<?php

namespace App\Verso\Install\Env;

// What to write into an env file: the file path and the keys to set.
final readonly class EnvWriteInput
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function __construct(
        public string $path,
        public array $values,
    ) {}
}
