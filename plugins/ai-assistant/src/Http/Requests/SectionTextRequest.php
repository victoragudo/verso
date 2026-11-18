<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the section and the business description sent to write new section texts.
class SectionTextRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'array'],
            'section.id' => ['required', 'string', 'max:64'],
            'section.type' => ['required', 'string', 'max:64'],
            'section.children' => ['present', 'array'],
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

    /**
     * @return array<mixed>
     */
    public function section(): array
    {
        $section = $this->input('section', []);

        return is_array($section) ? $section : [];
    }
}
