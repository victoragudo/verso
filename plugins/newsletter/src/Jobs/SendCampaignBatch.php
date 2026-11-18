<?php

namespace Verso\Plugins\Newsletter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Verso\Plugins\Newsletter\CampaignRenderer;
use Verso\Plugins\Newsletter\Enums\DeliveryStatus;
use Verso\Plugins\Newsletter\Mail\CampaignMail;
use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\Models\Delivery;
use Verso\Plugins\Newsletter\NewsletterSettings;

// Sends one batch of campaign emails. A failed email is marked and the batch goes on.
class SendCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  list<int>  $deliveryIds
     */
    public function __construct(public int $campaignId, public array $deliveryIds) {}

    public function handle(CampaignRenderer $renderer, NewsletterSettings $settings): void
    {
        $campaign = Campaign::query()->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $deliveries = Delivery::query()
            ->with('subscriber')
            ->whereIn('id', $this->deliveryIds)
            ->where('status', DeliveryStatus::Queued)
            ->get();

        foreach ($deliveries as $delivery) {
            // People who left after the campaign started are skipped.
            if (! $delivery->subscriber->isSubscribed()) {
                $delivery->update(['status' => DeliveryStatus::Failed, 'error' => 'Unsubscribed before sending.']);

                continue;
            }

            try {
                $unsubscribeUrl = $delivery->subscriber->unsubscribeUrl();

                Mail::to($delivery->subscriber->email)->send(new CampaignMail(
                    subjectLine: $campaign->subject,
                    body: $renderer->email($campaign, $unsubscribeUrl, $delivery),
                    unsubscribeUrl: $unsubscribeUrl,
                    fromAddress: $settings->fromAddress(),
                    fromName: $settings->fromName(),
                ));

                $delivery->update(['status' => DeliveryStatus::Sent, 'sent_at' => now()]);
            } catch (Throwable $exception) {
                $delivery->update(['status' => DeliveryStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 500)]);
            }
        }

        $campaign->finishIfDone();
    }
}
