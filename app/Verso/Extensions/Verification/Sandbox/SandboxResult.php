<?php

namespace App\Verso\Extensions\Verification\Sandbox;

// What happened when a plugin was booted, or a theme rendered, in the sandbox process.
final readonly class SandboxResult
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const TIMEOUT = 'timeout';

    public const CRASHED = 'crashed';

    public const SKIPPED = 'skipped';

    /**
     * @param  list<string>  $migrated  migrations that ran up
     * @param  list<string>  $rolledBack  migrations that ran down
     * @param  list<array{uri: string, name: string|null, methods: list<string>}>  $routes  routes the plugin added
     * @param  array<string, list<string>>  $registered  content types, taxonomies and blocks the plugin added
     * @param  list<array{stage: string, message: string, file: string|null, line: int|null}>  $errors
     * @param  list<array{name: string, path: string, status: int, seconds: float, bytes: int, html: string}>  $pages  pages a theme rendered
     */
    public function __construct(
        public string $status,
        public float $seconds = 0.0,
        public array $migrated = [],
        public array $rolledBack = [],
        public array $routes = [],
        public array $registered = [],
        public array $errors = [],
        public string $output = '',
        public array $pages = [],
    ) {}

    public static function skipped(): self
    {
        return new self(self::SKIPPED);
    }

    // First line of the process output, for short error messages.
    public function firstOutputLine(): string
    {
        $line = trim(strtok(trim($this->output), "\n") ?: '');

        return $line === '' ? 'no output.' : $line;
    }

    public function passed(): bool
    {
        return $this->status === self::PASSED;
    }

    /**
     * Read the JSON the sandbox command prints.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data, float $seconds, string $output): self
    {
        $errors = [];

        foreach ((array) ($data['errors'] ?? []) as $error) {
            if (is_array($error)) {
                $errors[] = [
                    'stage' => (string) ($error['stage'] ?? 'boot'),
                    'message' => (string) ($error['message'] ?? ''),
                    'file' => isset($error['file']) ? (string) $error['file'] : null,
                    'line' => isset($error['line']) ? (int) $error['line'] : null,
                ];
            }
        }

        $routes = [];

        foreach ((array) ($data['routes'] ?? []) as $route) {
            if (is_array($route) && isset($route['uri'])) {
                $routes[] = [
                    'uri' => (string) $route['uri'],
                    'name' => isset($route['name']) ? (string) $route['name'] : null,
                    'methods' => array_values(array_map(strval(...), (array) ($route['methods'] ?? []))),
                ];
            }
        }

        $registered = [];

        foreach ((array) ($data['registered'] ?? []) as $kind => $keys) {
            $registered[(string) $kind] = array_values(array_map(strval(...), (array) $keys));
        }

        $pages = [];

        foreach ((array) ($data['pages'] ?? []) as $page) {
            if (is_array($page) && isset($page['name'])) {
                $pages[] = [
                    'name' => (string) $page['name'],
                    'path' => (string) ($page['path'] ?? ''),
                    'status' => (int) ($page['status'] ?? 0),
                    'seconds' => round((float) ($page['seconds'] ?? 0), 3),
                    'bytes' => (int) ($page['bytes'] ?? 0),
                    'html' => (string) ($page['html'] ?? ''),
                ];
            }
        }

        return new self(
            status: $errors === [] ? self::PASSED : self::FAILED,
            seconds: $seconds,
            migrated: array_values(array_map(strval(...), (array) ($data['migrated'] ?? []))),
            rolledBack: array_values(array_map(strval(...), (array) ($data['rolled_back'] ?? []))),
            routes: $routes,
            registered: $registered,
            errors: $errors,
            output: $output,
            pages: $pages,
        );
    }

    /**
     * Data stored with the report.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'seconds' => round($this->seconds, 2),
            'migrated' => $this->migrated,
            'rolledBack' => $this->rolledBack,
            'routes' => $this->routes,
            'registered' => $this->registered,
            'errors' => $this->errors,
            'output' => $this->output,
            'pages' => $this->pages,
        ];
    }
}
