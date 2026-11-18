<?php

namespace App\Verso\Core\Models;

use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Sends visitors from an old path to a new path.
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['from_path', 'to_path', 'status_code'])]
#[UseFactory(RedirectFactory::class)]
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    /** @var list<int> */
    public const ALLOWED_STATUS_CODES = [301, 302];

    /** @var array<string, mixed> */
    protected $attributes = [
        'hits' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits' => 'integer',
        ];
    }

    // Create or update a redirect. Old redirects that pointed to $from now point to $to, so there are no chains.
    public static function point(string $from, string $to, ?int $statusCode = null): self
    {
        $from = self::normalizePath($from);
        $to = self::normalizeTarget($to);
        $statusCode ??= (int) config('verso.redirects.default_status');

        if (! in_array($statusCode, self::ALLOWED_STATUS_CODES, true)) {
            throw new InvalidArgumentException("Redirect status code must be 301 or 302, {$statusCode} given.");
        }

        if ($from === $to) {
            throw new InvalidArgumentException("A redirect cannot point to itself ({$from}).");
        }

        self::query()->where('from_path', $to)->delete();
        self::query()->where('to_path', $from)->update(['to_path' => $to]);

        return self::query()->updateOrCreate(
            ['from_path' => $from],
            ['to_path' => $to, 'status_code' => $statusCode],
        );
    }

    // Find the redirect for a request path, if any.
    public static function findForPath(string $path): ?self
    {
        return self::query()->where('from_path', self::normalizePath($path))->first();
    }

    // Paths always start with one slash and never end with one ("/old-post").
    public static function normalizePath(string $path): string
    {
        $path = (string) parse_url(trim($path), PHP_URL_PATH);
        $path = '/'.trim($path, '/');

        return rawurldecode($path);
    }

    // Targets can be full URLs or local paths.
    public static function normalizeTarget(string $target): string
    {
        $target = trim($target);

        if (preg_match('#^https?://#i', $target) === 1) {
            return $target;
        }

        return self::normalizePath($target);
    }

    public function recordHit(): void
    {
        $this->newQuery()->whereKey($this->getKey())->increment('hits');
        $this->hits++;
    }

    public function isPermanent(): bool
    {
        return $this->status_code === 301;
    }
}
