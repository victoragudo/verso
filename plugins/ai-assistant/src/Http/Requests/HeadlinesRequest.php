<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the current headline, the text around it and the business description.
class HeadlinesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:300'],
            'context' => ['nullable', 'string', 'max:'.(int) config('ai-assistant.limits.max_input_chars')],
            'business' => BusinessRequest::descriptionRules(required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return BusinessRequest::descriptionMessages();
    }
}
