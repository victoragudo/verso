<?php

namespace Verso\Plugins\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the business description saved for the page editor helper.
class BusinessRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business' => self::descriptionRules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::descriptionMessages();
    }

    /**
     * Rules of the business description field, shared by the helper actions.
     *
     * @return list<string>
     */
    public static function descriptionRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.(int) config('ai-assistant.limits.max_business_chars')];
    }

    /**
     * @return array<string, string>
     */
    public static function descriptionMessages(): array
    {
        return [
            'business.required' => __('ai-assistant::Tell the helper in a few words what your business does.'),
            'business.max' => __('ai-assistant::Keep the description of your business shorter (at most :max characters).'),
        ];
    }
}
