<?php

namespace App\Verso\Install\Env;

// Which keys an env write changed in place and which it added at the end.
final readonly class EnvWriteOutput
{
    /**
     * @param  list<string>  $updated
     * @param  list<string>  $added
     */
    public function __construct(
        public array $updated,
        public array $added,
    ) {}
}
