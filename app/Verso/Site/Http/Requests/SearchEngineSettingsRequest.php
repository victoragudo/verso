<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Media\Models\Media;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\Site\Support\RobotsTxtRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the search engine settings: robots.txt, titles, defaults, schema.org, verification and IndexNow.
class SearchEngineSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManagePermalinks->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        $verification = 'max:'.(int) config('verso.seo.verification_max_length');
        $keyLength = 'between:'.(int) config('verso.seo.indexnow.key_min_length').','.(int) config('verso.seo.indexnow.key_max_length');

        return [
            'discourage' => ['boolean'],
            'robots_txt' => ['nullable', 'string', 'max:'.(int) config('verso.site.robots_txt_max_length'), function (string $attribute, mixed $value, Closure $fail): void {
                $problem = app(RobotsTxtRules::class)->problem((string) $value);

                if ($problem !== null) {
                    $fail($problem);
                }
            }],
            'title_format' => ['required', 'string', 'max:'.(int) config('verso.seo.title_max_length'), 'regex:/\{title\}/'],
            'title_separator' => ['required', 'string', 'max:'.(int) config('verso.seo.separator_max_length')],
            'default_description' => ['nullable', 'string', 'max:'.(int) config('verso.seo.description_max_length')],
            'default_image' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'schema_type' => ['required', Rule::in(SeoSettings::SCHEMA_TYPES)],
            'schema_name' => ['nullable', 'string', 'max:'.(int) config('verso.seo.schema_name_max_length')],
            'schema_logo' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'schema_same_as' => ['present', 'array', 'max:'.(int) config('verso.seo.same_as_max')],
            'schema_same_as.*' => ['url:https,http', 'max:255'],
            'verification_google' => ['nullable', 'string', $verification, 'regex:/^[A-Za-z0-9_-]+$/'],
            'verification_bing' => ['nullable', 'string', $verification, 'regex:/^[A-Za-z0-9_-]+$/'],
            'indexnow_enabled' => ['boolean'],
            'indexnow_key' => ['nullable', 'required_if_accepted:indexnow_enabled', 'string', $keyLength, 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title_format.regex' => __('The title format needs the {title} token.'),
            'verification_google.regex' => __('Paste only the code from the content="..." part.'),
            'verification_bing.regex' => __('Paste only the code from the content="..." part.'),
            'indexnow_key.regex' => __('Use letters, numbers and dashes only.'),
            'indexnow_key.required_if_accepted' => __('Create a key to turn IndexNow on.'),
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            SeoSettings::DISCOURAGE => $this->boolean('discourage'),
            SeoSettings::ROBOTS_TXT => str_replace("\r\n", "\n", (string) $this->validated('robots_txt')),
            SeoSettings::TITLE_FORMAT => trim((string) $this->validated('title_format')),
            SeoSettings::TITLE_SEPARATOR => (string) $this->validated('title_separator'),
            SeoSettings::DEFAULT_DESCRIPTION => trim((string) $this->validated('default_description')),
            SeoSettings::DEFAULT_IMAGE => $this->filled('default_image') ? $this->integer('default_image') : null,
            SeoSettings::SCHEMA_TYPE => (string) $this->validated('schema_type'),
            SeoSettings::SCHEMA_NAME => trim((string) $this->validated('schema_name')),
            SeoSettings::SCHEMA_LOGO => $this->filled('schema_logo') ? $this->integer('schema_logo') : null,
            SeoSettings::SCHEMA_SAME_AS => array_values(array_map(strval(...), (array) $this->validated('schema_same_as'))),
            SeoSettings::VERIFICATION_GOOGLE => trim((string) $this->validated('verification_google')),
            SeoSettings::VERIFICATION_BING => trim((string) $this->validated('verification_bing')),
            SeoSettings::INDEXNOW_ENABLED => $this->boolean('indexnow_enabled'),
            SeoSettings::INDEXNOW_KEY => trim((string) $this->validated('indexnow_key')),
        ];
    }
}
