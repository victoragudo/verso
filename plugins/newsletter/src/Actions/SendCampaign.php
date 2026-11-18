<?php

namespace Verso\Plugins\Newsletter\Actions;

use Illuminate\Support\Facades\DB;
use LogicException;
use Verso\Plugins\Newsletter\CampaignRenderer;
use Verso\Plugins\Newsletter\Enums\CampaignStatus;
use Verso\Plugins\Newsletter\Jobs\SendCampaignBatch;
use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\Models\Delivery;
use Verso\Plugins\Newsletter\NewsletterSettings;

// Starts sending a draft: one delivery per recipient, then queued batches with a delay between them.
class SendCampaign
{
    public function __construct(
        protected NewsletterSettings $settings,
        protected CampaignRenderer $renderer,
    ) {}

    // Returns the number of batches queued.
    public function handle(Campaign $campaign): int
    {
        if (! $campaign->isDraft()) {
            throw new LogicException('Only draft campaigns can be sent.');
        }

        DB::transaction(function () use ($campaign): void {
            $campaign->recipients()->select('id')->chunkById(500, function ($subscribers) use ($campaign): void {
                foreach ($subscribers as $subscriber) {
                    Delivery::query()->create(['campaign_id' => $campaign->id, 'subscriber_id' => $subscriber->id]);
                }
            });

            $campaign->update([
                'status' => CampaignStatus::Sending,
                'started_at' => now(),
                'recipients_count' => $campaign->deliveries()->count(),
            ]);
        });

        if ($campaign->track_clicks) {
            $this->renderer->storeLinks($campaign);
        }

        $batches = $campaign->deliveries()->orderBy('id')->pluck('id')->chunk($this->settings->batchSize())->values();
        $queue = (string) config('newsletter.queue');

        foreach ($batches as $index => $ids) {
            $job = SendCampaignBatch::dispatch($campaign->id, array_values(array_map(intval(...), $ids->all())))->delay(now()->addSeconds($index * $this->settings->batchDelay()));

            if ($queue !== '') {
                $job->onQueue($queue);
            }
        }

        $campaign->finishIfDone();

        return $batches->count();
    }
}
