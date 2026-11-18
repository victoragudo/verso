<?php

namespace App\Verso\Install\Database;

// What PdoConnector needs to open a connection.
final readonly class PdoConnection
{
    public function __construct(
        public string $dsn,
        public string $username,
        public string $password,
        public int $timeout,
    ) {}
}
