<?php

namespace Verso\Plugins\Newsletter\Http\Controllers\Admin;

use App\Models\User;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Newsletter\Actions\SendCampaign;
use Verso\Plugins\Newsletter\Actions\SendTestCampaign;
use Verso\Plugins\Newsletter\Enums\CampaignStatus;
use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\Models\CampaignLink;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\NewsletterSettings;
use Verso\Plugins\Newsletter\Support\QueueHeartbeat;

// Admin pages to write, test, send and measure campaigns.
class CampaignController
{
    public function __construct(protected NewsletterSettings $settings) {}

    public function index(): Response
    {
        $campaigns = Campaign::query()
            ->with('list:id,name')
            ->latest('id')
            ->paginate((int) config('newsletter.per_page'))
            ->through(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'status' => $campaign->status->value,
                'list' => $campaign->list?->name,
                'recipients' => $campaign->recipients_count,
                'sent_at' => $campaign->sent_at?->toIso8601String(),
                'updated_at' => $campaign->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/plugins/newsletter/campaigns', [
            'campaigns' => $campaigns,
            'urls' => $this->urls(),
            'queueNotice' => app(QueueHeartbeat::class)->notice(),
        ]);
    }

    public function create(): Response
    {
        return $this->editor(null);
    }

    public function store(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $campaign = Campaign::query()->create([...$this->validated($request, $user), 'created_by' => $user->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::Campaign saved.')]);

        return redirect()->route('admin.newsletter.campaigns.edit', $campaign);
    }

    public function edit(Campaign $campaign): Response|RedirectResponse
    {
        return $campaign->isDraft() ? $this->editor($campaign) : redirect()->route('admin.newsletter.campaigns.show', $campaign);
    }

    public function update(Request $request, #[CurrentUser] User $user, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isDraft(), 409, __('newsletter::This campaign was already sent.'));

        $campaign->update($this->validated($request, $user));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::Campaign saved.')]);

        return back();
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->status === CampaignStatus::Sending, 409, __('newsletter::Wait until the campaign is sent.'));

        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::Campaign deleted.')]);

        return redirect()->route('admin.newsletter.campaigns.index');
    }

    public function test(Request $request, Campaign $campaign, SendTestCampaign $send): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        $send->handle($campaign, (string) $data['email']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::Test email sent to :email.', ['email' => (string) $data['email']])]);

        return back();
    }

    public function send(Campaign $campaign, SendCampaign $send): RedirectResponse
    {
        abort_unless($campaign->isDraft(), 409, __('newsletter::This campaign was already sent.'));

        if (! $campaign->recipients()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('newsletter::Nobody would get this campaign. Pick a list with subscribed people.')]);

            return back();
        }

        $send->handle($campaign);

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('newsletter::{count, plural, one {Sending to # subscriber.} other {Sending to # subscribers.}}', ['count' => $campaign->recipients_count])]);

        return redirect()->route('admin.newsletter.campaigns.show', $campaign);
    }

    public function show(Campaign $campaign): Response
    {
        return Inertia::render('admin/plugins/newsletter/campaign', [
            'queueNotice' => app(QueueHeartbeat::class)->notice(),
            'campaign' => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'status' => $campaign->status->value,
                'list' => $campaign->list?->name,
                'track_opens' => $campaign->track_opens,
                'track_clicks' => $campaign->track_clicks,
                'started_at' => $campaign->started_at?->toIso8601String(),
                'sent_at' => $campaign->sent_at?->toIso8601String(),
            ],
            'stats' => $campaign->stats(),
            'links' => $campaign->links()->orderByDesc('clicks')->get()->map(fn (CampaignLink $link): array => [
                'id' => $link->id,
                'url' => $link->url,
                'clicks' => $link->clicks,
            ]),
            'urls' => [...$this->urls(), 'edit' => route('admin.newsletter.campaigns.edit', $campaign)],
        ]);
    }

    protected function editor(?Campaign $campaign): Response
    {
        return Inertia::render('admin/plugins/newsletter/campaign-edit', [
            'queueNotice' => app(QueueHeartbeat::class)->notice(),
            'campaign' => $campaign === null ? null : [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'preheader' => $campaign->preheader,
                'blocks' => $campaign->blocks ?? [],
                'list_id' => $campaign->list_id,
                'track_opens' => $campaign->track_opens,
                'track_clicks' => $campaign->track_clicks,
                'recipients' => $campaign->recipients()->count(),
            ],
            'defaults' => ['track_opens' => (bool) $this->settings->get('track_opens'), 'track_clicks' => (bool) $this->settings->get('track_clicks')],
            'lists' => MailingList::query()->orderBy('name')->get(['id', 'name']),
            'pluginBlocks' => app(PluginBlockRegistry::class)->toArray(),
            'urls' => [
                ...$this->urls(),
                'save' => $campaign === null ? route('admin.newsletter.campaigns.store') : route('admin.newsletter.campaigns.update', $campaign),
                'test' => $campaign === null ? null : route('admin.newsletter.campaigns.test', $campaign),
                'send' => $campaign === null ? null : route('admin.newsletter.campaigns.send', $campaign),
                'destroy' => $campaign === null ? null : route('admin.newsletter.campaigns.destroy', $campaign),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:190'],
            'preheader' => ['nullable', 'string', 'max:190'],
            'blocks' => ['nullable', 'array', new ValidBlocks($user)],
            'list_id' => ['nullable', 'integer', Rule::exists(MailingList::class, 'id')],
            'track_opens' => ['boolean'],
            'track_clicks' => ['boolean'],
        ]);

        return [
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? null,
            'blocks' => array_values((array) $request->input('blocks', [])),
            'list_id' => $data['list_id'] ?? null,
            'track_opens' => (bool) ($data['track_opens'] ?? false),
            'track_clicks' => (bool) ($data['track_clicks'] ?? false),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function urls(): array
    {
        return [
            'index' => route('admin.newsletter.campaigns.index'),
            'create' => route('admin.newsletter.campaigns.create'),
            'campaign' => route('admin.newsletter.campaigns.show', ['campaign' => '__ID__']),
            'subscribers' => route('admin.newsletter.subscribers.index'),
            'settings' => route('admin.newsletter.settings.edit'),
        ];
    }
}
