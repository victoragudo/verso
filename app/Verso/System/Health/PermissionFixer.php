<?php

namespace App\Verso\System\Health;

use App\Verso\I18n\Translate;
use Closure;

// Runs planned fixes. It never touches a path outside the project and never follows links out of it.
class PermissionFixer
{
    /**
     * @param  Closure(): bool  $linkStorage  creates public/storage (php artisan storage:link)
     */
    public function __construct(
        protected PermissionInspector $inspector,
        protected Closure $linkStorage,
    ) {}

    /**
     * Run (or with $dryRun only describe) the fixes.
     *
     * @param  list<PlannedFix>  $fixes
     * @return list<array{description: string, done: bool, error: string|null}>
     */
    public function run(array $fixes, bool $dryRun = true): array
    {
        $results = [];

        foreach ($fixes as $fix) {
            $error = $this->problem($fix);

            if ($error === null && ! $dryRun) {
                $error = $this->apply($fix) ? null : Translate::text('PHP could not do this. Use the shell command instead.');
            }

            $results[] = ['description' => $fix->description(), 'done' => ! $dryRun && $error === null, 'error' => $error];
        }

        return $results;
    }

    // Why the fix is not allowed, or null.
    protected function problem(PlannedFix $fix): ?string
    {
        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $fix->path) === 1 || str_starts_with($fix->path, '/')) {
            return Translate::text('The path must stay inside the project.');
        }

        $path = $this->inspector->absolute($fix->path);

        return match ($fix->type) {
            PlannedFix::CHMOD => match (true) {
                is_link($path) => Translate::text('Links are never changed.'),
                ! file_exists($path) => Translate::text('The path does not exist.'),
                ! $this->inspector->inside((string) realpath($path)) => Translate::text('The path is outside the project.'),
                $fix->mode === null || $fix->mode > 0777 => Translate::text('The mode is not valid.'),
                default => null,
            },
            PlannedFix::MKDIR => file_exists($path) ? Translate::text('The folder already exists.') : ($this->parentInside($path) ? null : Translate::text('The folder would be outside the project.')),
            PlannedFix::STORAGE_LINK => file_exists($path) || is_link($path) ? Translate::text('public/storage already exists.') : null,
            default => Translate::text('Unknown fix.'),
        };
    }

    protected function apply(PlannedFix $fix): bool
    {
        $path = $this->inspector->absolute($fix->path);

        return match ($fix->type) {
            PlannedFix::CHMOD => @chmod($path, (int) $fix->mode),
            PlannedFix::MKDIR => @mkdir($path, (int) $fix->mode, true) && @chmod($path, (int) $fix->mode),
            default => ($this->linkStorage)(),
        };
    }

    protected function parentInside(string $path): bool
    {
        $parent = dirname($path);

        while (! file_exists($parent) && $parent !== dirname($parent)) {
            $parent = dirname($parent);
        }

        $real = realpath($parent);

        return $real !== false && $this->inspector->inside($real);
    }
}
