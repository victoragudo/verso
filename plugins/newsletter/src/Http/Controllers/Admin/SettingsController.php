<?php

namespace Verso\Plugins\Newsletter\Http\Controllers\Admin;

use App\Verso\Shortcodes\ShortcodeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\NewsletterServiceProvider;
use Verso\Plugins\Newsletter\NewsletterSettings;
use Verso\Plugins\Newsletter\Support\QueueHeartbeat;

// Admin page for sending options: batches, sender and tracking.
class SettingsController
{
    public function __construct(protected NewsletterSettings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/plugins/newsletter/settings', [
            'settings' => $this->settings->toArray(),
            'queueNotice' => app(QueueHeartbeat::class)->notice(),
            'signups' => $this->signups(),
            'defaults' => ['from_address' => (string) config('mail.from.address'), 'from_name' => (string) config('mail.from.name')],
            'urls' => ['update' => route('admin.newsletter.settings.update'), 'campaigns' => route('admin.newsletter.campaigns.index')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batch_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'batch_delay' => ['required', 'integer', 'min:0', 'max:86400'],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'track_opens' => ['boolean'],
            'track_clicks' => ['boolean'],
        ]);

        $this->settings->save([
            ...$data,
            'from_address' => (string) ($data['from_address'] ?? ''),
            'from_name' => (string) ($data['from_name'] ?? ''),
            'track_opens' => (bool) ($data['track_opens'] ?? false),
            'track_clicks' => (bool) ($data['track_clicks'] ?? false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::Newsletter settings saved.')]);

        return back();
    }

    /**
     * A shortcode for the signup form of each list, and one without a list.
     *
     * @return list<array{label: string, shortcode: string, blade: string}>
     */
    protected function signups(): array
    {
        $shortcodes = app(ShortcodeRegistry::class);
        $lists = MailingList::query()->orderBy('name')->get(['name', 'slug']);
        $rows = [['label' => __('newsletter::All subscribers'), 'attrs' => []]];

        foreach ($lists as $list) {
            $rows[] = ['label' => $list->name, 'attrs' => ['list' => $list->slug]];
        }

        return array_map(fn (array $row): array => [
            'label' => $row['label'],
            'shortcode' => $shortcodes->snippet(NewsletterServiceProvider::BLOCK, $row['attrs']),
            'blade' => $shortcodes->bladeSnippet(NewsletterServiceProvider::BLOCK, $row['attrs']),
        ], $rows);
    }
}
