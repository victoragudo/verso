<?php

namespace App\Verso\Library\Models;

use App\Models\User;
use App\Verso\Core\Database\JsonArray;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One saved item of the design library. A synced block is drawn from here wherever it is used;
 * a pattern is copied into the page when someone adds it.
 *
 * @property int $id
 * @property string $kind
 * @property string $title
 * @property string $category
 * @property list<string> $keywords
 * @property array<int, mixed> $blocks
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kind', 'title', 'category', 'keywords', 'blocks', 'user_id'])]
class LibraryItem extends Model
{
    public const string SYNCED = 'synced';

    public const string PATTERN = 'pattern';

    public const array KINDS = [self::SYNCED, self::PATTERN];

    protected function casts(): array
    {
        return ['keywords' => JsonArray::class, 'blocks' => JsonArray::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{id: int, kind: string, title: string, category: string, keywords: list<string>, blocks: array<int, mixed>, updatedAt: string|null} */
    public function toEditor(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'category' => $this->category,
            'keywords' => array_map('strval', $this->keywords),
            'blocks' => $this->blocks,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
