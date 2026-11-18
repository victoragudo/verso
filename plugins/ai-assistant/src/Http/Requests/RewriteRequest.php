<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Verso\Plugins\AiAssistant\AiAssistant;

// Checks the selected text and how to rewrite it.
class RewriteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.(int) config('ai-assistant.limits.max_input_chars')],
            'mode' => ['required', 'string', Rule::in(AiAssistant::REWRITE_MODES)],
        ];
    }
}
