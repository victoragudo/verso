<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Site\Sitemap\SitemapSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the sitemap options.
class SitemapSettingsRequest extends FormRequest
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
            'enabled' => ['boolean'],
            'types' => ['present', 'array'],
            'types.*' => ['string', Rule::in(app(ContentTypeRegistry::class)->names())],
            'taxonomies' => ['present', 'array'],
            'taxonomies.*' => ['string', Rule::in(array_keys(app(TaxonomyRegistry::class)->all()))],
            'excluded_entries' => ['present', 'array'],
            'excluded_entries.*' => ['integer', Rule::exists(Entry::class, 'id')],
            'include_images' => ['boolean'],
            'max_urls' => ['required', 'integer', 'min:1', 'max:'.(int) config('verso.sitemap.max_urls_limit')],
            'last_modified' => ['required', Rule::in(SitemapSettings::LAST_MODIFIED_SOURCES)],
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            SitemapSettings::ENABLED => $this->boolean('enabled'),
            SitemapSettings::TYPES => array_values(array_unique(array_map(strval(...), (array) $this->validated('types')))),
            SitemapSettings::TAXONOMIES => array_values(array_unique(array_map(strval(...), (array) $this->validated('taxonomies')))),
            SitemapSettings::EXCLUDED_ENTRIES => array_values(array_unique(array_map(intval(...), (array) $this->validated('excluded_entries')))),
            SitemapSettings::INCLUDE_IMAGES => $this->boolean('include_images'),
            SitemapSettings::MAX_URLS => $this->integer('max_urls'),
            SitemapSettings::LAST_MODIFIED => (string) $this->validated('last_modified'),
        ];
    }
}
