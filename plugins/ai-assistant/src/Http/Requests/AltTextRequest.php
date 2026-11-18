<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the media library image to describe.
class AltTextRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'exists:media,id'],
        ];
    }
}
