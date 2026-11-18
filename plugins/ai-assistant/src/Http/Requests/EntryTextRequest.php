<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// Checks the title and blocks of the entry being edited.
class EntryTextRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:300'],
            'blocks' => ['present', 'array'],
        ];
    }

    // The entry needs a title or some content to work with.
    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (trim((string) $this->input('title')) === '' && $this->input('blocks', []) === []) {
                    $validator->errors()->add('blocks', __('ai-assistant::Write a title or some content first.'));
                }
            },
        ];
    }

    /**
     * @return array<mixed>
     */
    public function blocks(): array
    {
        $blocks = $this->input('blocks', []);

        return is_array($blocks) ? $blocks : [];
    }
}
