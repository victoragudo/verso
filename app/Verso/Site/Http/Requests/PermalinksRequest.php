<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Site\Permalinks\PermalinkClashes;
use App\Verso\Site\Permalinks\PermalinkValues;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// Validates the URL structure form, then checks it for clashes.
class PermalinksRequest extends FormRequest
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
        $max = (int) config('verso.permalinks.max_length');

        return [
            'structures' => ['required', 'array'],
            'structures.*' => ['required', 'string', 'max:'.$max],
            'archive_bases' => ['nullable', 'array'],
            'archive_bases.*' => ['nullable', 'string', 'max:'.$max],
            'taxonomy_bases' => ['nullable', 'array'],
            'taxonomy_bases.*' => ['nullable', 'string', 'max:'.$max],
            'trailing_slash' => ['boolean'],
            'lowercase' => ['boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (app(PermalinkClashes::class)->check($this->values()) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public function values(): PermalinkValues
    {
        return new PermalinkValues(
            structures: $this->strings('structures'),
            archiveBases: $this->strings('archive_bases'),
            taxonomyBases: $this->strings('taxonomy_bases'),
            trailingSlash: $this->boolean('trailing_slash'),
            lowercase: $this->boolean('lowercase'),
        );
    }

    /** @return array<string, string> */
    protected function strings(string $key): array
    {
        $values = [];

        foreach ((array) $this->input($key, []) as $name => $value) {
            $values[(string) $name] = mb_strtolower(trim((string) $value));
        }

        return $values;
    }
}
