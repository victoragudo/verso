<?php

namespace App\Verso\Install\Database;

// The result of a database connection test.
final readonly class ConnectionReport
{
    /**
     * @param  list<string>  $versoTables
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public ?ConnectionProblem $problem = null,
        public string $serverVersion = '',
        public string $serverKind = '',
        public bool $canCreateDatabase = false,
        public int $tableCount = 0,
        public array $versoTables = [],
        public bool $fileMissing = false,
    ) {}

    public static function failed(ConnectionProblem $problem, string $message, bool $canCreateDatabase = false, string $serverVersion = '', string $serverKind = ''): self
    {
        return new self(
            ok: false,
            message: $message,
            problem: $problem,
            serverVersion: $serverVersion,
            serverKind: $serverKind,
            canCreateDatabase: $canCreateDatabase,
        );
    }

    // True when tables of an old Verso install would clash with a new install.
    public function hasVersoTables(): bool
    {
        return $this->versoTables !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'problem' => $this->problem?->value,
            'hint' => $this->problem?->hint(),
            'server_version' => $this->serverVersion,
            'server_kind' => $this->serverKind,
            'can_create_database' => $this->canCreateDatabase,
            'table_count' => $this->tableCount,
            'verso_tables' => $this->versoTables,
            'file_missing' => $this->fileMissing,
        ];
    }
}
