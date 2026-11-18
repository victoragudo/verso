<?php

namespace Verso\Plugins\Forms\Models;

use App\Verso\Core\Database\JsonArray;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Verso\Plugins\Forms\Enums\SubmissionStatus;

// One message sent with a form.
/**
 * @property int $id
 * @property int $form_id
 * @property SubmissionStatus $status
 * @property array<mixed>|null $data
 * @property array<mixed>|null $files
 * @property string|null $email
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property string|null $page_url
 * @property string|null $spam_reason
 * @property string|null $search_text
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Form $form
 */
class Submission extends Model
{
    protected $table = 'form_submissions';

    protected $attributes = ['status' => 'new'];

    protected $fillable = ['form_id', 'status', 'data', 'files', 'email', 'ip_hash', 'user_agent', 'page_url', 'spam_reason', 'search_text', 'read_at'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'data' => JsonArray::class,
            'files' => JsonArray::class,
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Submission $submission): void {
            $submission->email = $submission->email !== null ? mb_strtolower(trim($submission->email)) : null;
            $submission->search_text = $submission->buildSearchText();
        });

        // Uploaded files go with the submission, also for privacy requests.
        static::deleting(function (Submission $submission): void {
            $submission->deleteFiles();
        });
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    public function scopeInbox(Builder $query): Builder
    {
        return $query->where('status', '!=', SubmissionStatus::Spam->value);
    }

    // Opening a new message marks it as read.
    public function markRead(): void
    {
        if ($this->status === SubmissionStatus::New) {
            $this->forceFill(['status' => SubmissionStatus::Read, 'read_at' => now()])->save();
        }
    }

    public function moveTo(SubmissionStatus $status): void
    {
        $this->forceFill([
            'status' => $status,
            'read_at' => $status === SubmissionStatus::New ? null : ($this->read_at ?? now()),
        ])->save();
    }

    public function value(string $name): mixed
    {
        $data = is_array($this->data) ? $this->data : [];

        return $data[$name] ?? null;
    }

    // A value as one line of text: lists are joined, booleans become Yes or No.
    public function text(string $name): string
    {
        $value = $this->value($name);
        $file = is_array($this->files) ? ($this->files[$name] ?? null) : null;

        return match (true) {
            is_array($file) => (string) ($file['name'] ?? ''),
            is_array($value) => implode(', ', array_map('strval', $value)),
            is_bool($value) => $value ? __('forms::Yes') : __('forms::No'),
            default => (string) $value,
        };
    }

    // All values in one plain text column, so the inbox search works the same on every database.
    public function buildSearchText(): string
    {
        $parts = [];

        foreach (is_array($this->data) ? $this->data : [] as $value) {
            $parts[] = is_array($value) ? implode(' ', array_map('strval', $value)) : (is_scalar($value) ? (string) $value : '');
        }

        foreach (is_array($this->files) ? $this->files : [] as $file) {
            $parts[] = is_array($file) ? (string) ($file['name'] ?? '') : '';
        }

        return mb_substr(implode(' ', array_filter($parts, fn (string $part): bool => $part !== '')), 0, 10000);
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk((string) config('forms.disk'));

        foreach (is_array($this->files) ? $this->files : [] as $file) {
            if (is_array($file) && is_string($file['path'] ?? null)) {
                $disk->delete($file['path']);
            }
        }
    }
}
