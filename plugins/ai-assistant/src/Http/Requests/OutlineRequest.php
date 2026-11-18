<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the title (and optional notes) sent to draft an outline.
class OutlineRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:'.(int) config('ai-assistant.limits.max_input_chars')],
        ];
    }
}
