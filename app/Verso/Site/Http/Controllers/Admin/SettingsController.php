<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Site\Actions\UpdateSiteSettings;
use App\Verso\Site\Http\Requests\SiteSettingsRequest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin page for the general site settings: identity, reading and discussion.
class SettingsController extends Controller
{
    public function edit(UpdateSiteSettings $settings): Response
    {
        Gate::authorize(Capability::ManageGeneralSettings->value);

        $values = $settings->current();

        return Inertia::render('admin/site/settings', [
            'settings' => [
                'name' => (string) $values['site.name'],
                'tagline' => (string) $values['site.tagline'],
                'url' => (string) $values['site.url'],
                'locale' => (string) $values['site.locale'],
                'timezone' => (string) $values['site.timezone'],
                'posts_per_page' => (int) $values['reading.posts_per_page'],
                'front_page' => $values['reading.front_page'] === null ? null : (int) $values['reading.front_page'],
                'comments_enabled' => (bool) $values['comments.enabled'],
                'revisions_limit' => (int) $values['content.revisions_limit'],
            ],
            'options' => [
                'locales' => array_values((array) config('verso.site.locales')),
                'timezones' => DateTimeZone::listIdentifiers(),
                'pages' => Entry::query()->ofType('page')->published()->orderBy('title')->get(['id', 'title'])
                    ->map(fn (Entry $page): array => ['id' => $page->id, 'title' => $page->title])
                    ->all(),
            ],
            'seoUrl' => route('admin.site.permalinks.edit'),
        ]);
    }

    public function update(SiteSettingsRequest $request, UpdateSiteSettings $settings): RedirectResponse
    {
        $settings->handle($request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('admin.site.settings.edit');
    }
}
