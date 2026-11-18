<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the general settings form.
class SiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageGeneralSettings->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'locale' => ['required', Rule::in((array) config('verso.site.locales'))],
            'timezone' => ['required', 'timezone:all'],
            'posts_per_page' => ['required', 'integer', 'min:1', 'max:'.(int) config('verso.site.max_posts_per_page')],
            'front_page' => ['nullable', 'integer', Rule::exists(Entry::class, 'id')->where('type', 'page')->whereNull('deleted_at')],
            'comments_enabled' => ['boolean'],
            'revisions_limit' => ['required', 'integer', 'min:0', 'max:'.(int) config('verso.site.max_revisions_limit')],
        ];
    }

    /**
     * The form values as setting keys, for UpdateSiteSettings.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'site.name' => trim((string) $this->validated('name')),
            'site.tagline' => trim((string) $this->validated('tagline')),
            'site.url' => rtrim((string) $this->validated('url'), '/'),
            'site.locale' => (string) $this->validated('locale'),
            'site.timezone' => (string) $this->validated('timezone'),
            'reading.posts_per_page' => $this->integer('posts_per_page'),
            'reading.front_page' => $this->filled('front_page') ? $this->integer('front_page') : null,
            'comments.enabled' => $this->boolean('comments_enabled'),
            'content.revisions_limit' => $this->integer('revisions_limit'),
        ];
    }
}
