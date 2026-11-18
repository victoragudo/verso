<?php

namespace Verso\Plugins\Newsletter\Actions;

use Illuminate\Support\Facades\Mail;
use Verso\Plugins\Newsletter\CampaignRenderer;
use Verso\Plugins\Newsletter\Mail\CampaignMail;
use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\NewsletterSettings;

// Sends one copy of a campaign to a test address. Nothing is tracked or counted.
class SendTestCampaign
{
    public function __construct(
        protected NewsletterSettings $settings,
        protected CampaignRenderer $renderer,
    ) {}

    public function handle(Campaign $campaign, string $email): void
    {
        $unsubscribeUrl = url('/');

        Mail::to($email)->send(new CampaignMail(
            subjectLine: '[Test] '.$campaign->subject,
            body: $this->renderer->email($campaign, $unsubscribeUrl),
            unsubscribeUrl: $unsubscribeUrl,
            fromAddress: $this->settings->fromAddress(),
            fromName: $this->settings->fromName(),
        ));
    }
}
