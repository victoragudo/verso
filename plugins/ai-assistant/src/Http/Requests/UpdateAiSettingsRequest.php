<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the AI assistant settings form.
class UpdateAiSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'max:300'],
            'model' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9.:@_-]*$/'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:'.(int) config('ai-assistant.limits.max_tokens_ceiling')],
            'rate_limit' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model.regex' => __('ai-assistant::Use a model id like :model.', ['model' => 'claude-sonnet-5-5']),
        ];
    }
}
