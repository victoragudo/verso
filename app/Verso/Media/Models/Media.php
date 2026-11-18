<?php

namespace App\Verso\Media\Models;

use App\Models\User;
use App\Verso\Core\Database\JsonArray;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\ImageUrl;
use App\Verso\Media\Policies\MediaPolicy;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded file stored on a disk.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property string|null $caption
 * @property int|null $uploaded_by
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $uploader
 */
#[Fillable(['disk', 'path', 'filename', 'mime_type', 'size', 'width', 'height', 'alt', 'caption', 'meta'])]
#[UseFactory(MediaFactory::class)]
#[UsePolicy(MediaPolicy::class)]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'uploaded_by' => 'integer',
            'meta' => JsonArray::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @param Builder<Media> $query */
    #[Scope]
    protected function ofType(Builder $query, MediaType $type): void
    {
        $prefix = $type->mimePrefix();

        if ($prefix !== null) {
            $query->where('mime_type', 'like', $prefix.'%');

            return;
        }

        $query->where(function (Builder $query): void {
            foreach (MediaType::cases() as $other) {
                if ($other->mimePrefix() !== null) {
                    $query->where('mime_type', 'not like', $other->mimePrefix().'%');
                }
            }
        });
    }

    // Finds media whose file name, alt text or caption contains the words.
    /** @param Builder<Media> $query */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        TextSearch::apply($query, ['filename', 'alt', 'caption'], $term);
    }

    public function type(): MediaType
    {
        return MediaType::fromMime($this->mime_type);
    }

    public function isImage(): bool
    {
        return $this->type() === MediaType::Image;
    }

    public function isVideo(): bool
    {
        return $this->type() === MediaType::Video;
    }

    // The file extension in lower case, like "jpg".
    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    // The direct URL of the original file on its disk.
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    // True when the file can be resized by the image route.
    public function isResizable(): bool
    {
        return $this->isImage() && $this->disk === config('verso.media.disk');
    }

    /**
     * A signed URL of a resized copy, or the original URL when it cannot be resized.
     *
     * @param  array<string, int|string>  $params
     */
    public function imageUrl(array $params = []): string
    {
        return $this->isResizable() ? app(ImageUrl::class)->make($this->path, $params) : $this->url();
    }

    // True when the file is still on its disk.
    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
