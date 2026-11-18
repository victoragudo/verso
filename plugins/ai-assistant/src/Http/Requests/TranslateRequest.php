<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Validation\Rule;
use Verso\Plugins\AiAssistant\AiAssistantServiceProvider;

// Checks the entry and the target language of a translation.
class TranslateRequest extends EntryTextRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'locale' => ['required', 'string', Rule::in(array_keys(AiAssistantServiceProvider::locales()))],
        ];
    }
}
