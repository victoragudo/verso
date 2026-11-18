<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Models\Redirect;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Validates the form to add a redirect.
class RedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManagePermalinks->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_path' => ['required', 'string', 'max:255', 'not_regex:#^[a-z][a-z0-9+.-]*://#i'],
            'to_path' => ['required', 'string', 'max:255', 'regex:#^(/|https?://)#i'],
            'status_code' => ['required', 'integer', Rule::in(Redirect::ALLOWED_STATUS_CODES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from_path.not_regex' => __('Use a path on this site, like /old-post.'),
            'to_path.regex' => __('Use a path starting with / or a full http(s) URL.'),
        ];
    }

    // A path cannot redirect to itself.
    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (Redirect::normalizePath($this->string('from_path')->toString()) === Redirect::normalizeTarget($this->string('to_path')->toString())) {
                    $validator->errors()->add('to_path', __('A redirect cannot point to itself.'));
                }
            },
        ];
    }
}
