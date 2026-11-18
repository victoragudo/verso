<?php

namespace App\Verso\Install\Pipeline;

use Illuminate\Filesystem\Filesystem;

// Keeps the progress of a running install in a small JSON file, so a failed step can be retried later.
class InstallProgress
{
    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public function __construct(
        protected Filesystem $files,
        protected string $path,
    ) {}

    public function exists(): bool
    {
        return $this->files->exists($this->path);
    }

    /**
     * Start tracking these steps. A started install keeps its progress.
     *
     * @param  list<string>  $steps
     */
    public function start(array $steps): void
    {
        $data = $this->read();
        $known = $data['steps'] ?? [];

        $data['steps'] = array_combine($steps, array_map(
            fn (string $step): string => in_array($known[$step] ?? null, [self::DONE, self::SKIPPED], true) ? $known[$step] : self::PENDING,
            $steps,
        ));
        $data['started_at'] ??= now()->toIso8601String();
        $data['error'] = null;

        $this->save($data);
    }

    /**
     * @return array<string, string>
     */
    public function steps(): array
    {
        return $this->read()['steps'] ?? [];
    }

    public function mark(string $step, string $status): void
    {
        $data = $this->read();
        $data['steps'][$step] = $status;

        if ($status !== self::FAILED) {
            $data['error'] = null;
        }

        $this->save($data);
    }

    public function fail(string $step, string $message): void
    {
        $data = $this->read();
        $data['steps'][$step] = self::FAILED;
        $data['error'] = ['step' => $step, 'message' => $message];

        $this->save($data);
    }

    /**
     * @return array{step: string, message: string}|null
     */
    public function error(): ?array
    {
        return $this->read()['error'] ?? null;
    }

    // Keep the hash of the one-time sign-in link for the new admin.
    public function rememberLogin(int $userId, string $nonce): void
    {
        $data = $this->read();
        $data['login'] = ['user' => $userId, 'nonce' => hash('sha256', $nonce)];

        $this->save($data);
    }

    // True once when the nonce matches. The progress file is removed afterwards.
    public function consumeLogin(int $userId, string $nonce): bool
    {
        $login = $this->read()['login'] ?? null;

        if (! is_array($login) || ($login['user'] ?? null) !== $userId || ! hash_equals((string) ($login['nonce'] ?? ''), hash('sha256', $nonce))) {
            return false;
        }

        $this->forget();

        return true;
    }

    public function forget(): void
    {
        $this->files->delete($this->path);
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $data = json_decode((string) $this->files->get($this->path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function save(array $data): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), true);
    }
}
