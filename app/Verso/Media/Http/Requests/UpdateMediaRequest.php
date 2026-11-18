<?php

namespace App\Verso\Media\Http\Requests;

use App\Verso\Media\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Validates the alt text and caption form.
class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media && ($this->user()?->can('update', $media) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:'.(int) config('verso.media.caption_max_length')],
        ];
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
