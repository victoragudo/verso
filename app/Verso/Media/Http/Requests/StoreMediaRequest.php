<?php

namespace App\Verso\Media\Http\Requests;

use App\Verso\Media\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

// Validates an upload. The type and size rules live in the UploadMedia action.
class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:'.(int) config('verso.media.caption_max_length')],
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    /**
     * @return array{alt: string|null, caption: string|null}
     */
    public function mediaData(): array
    {
        return [
            'alt' => $this->filled('alt') ? (string) $this->validated('alt') : null,
            'caption' => $this->filled('caption') ? (string) $this->validated('caption') : null,
        ];
    }
}
