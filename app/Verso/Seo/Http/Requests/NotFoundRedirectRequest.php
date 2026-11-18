<?php

namespace App\Verso\Seo\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Models\Redirect;
use App\Verso\Seo\Models\NotFoundHit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Validates the target of a redirect made from a row of the 404 monitor.
class NotFoundRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageSeo->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to_path' => ['required', 'string', 'max:255', 'regex:#^(/|https?://)#i'],
            'status_code' => ['sometimes', 'integer', Rule::in(Redirect::ALLOWED_STATUS_CODES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to_path.regex' => __('Use a path starting with / or a full http(s) URL.'),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hit = $this->route('hit');

                if ($validator->errors()->isEmpty() && $hit instanceof NotFoundHit
                    && Redirect::normalizeTarget($this->string('to_path')->toString()) === $hit->path) {
                    $validator->errors()->add('to_path', __('A redirect cannot point to itself.'));
                }
            },
        ];
    }
}
