<?php

namespace Verso\Plugins\Newsletter;

use App\Verso\Plugins\ContentRenderer;
use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\Models\CampaignLink;
use Verso\Plugins\Newsletter\Models\Delivery;

// Turns campaign blocks into email HTML, with optional tracking links and pixel.
class CampaignRenderer
{
    public function __construct(protected ContentRenderer $content) {}

    // The campaign body, rendered with the same block renderers as entries.
    public function body(Campaign $campaign): string
    {
        return $this->content->renderChildren(['children' => $campaign->blocks ?? []]);
    }

    /**
     * Every distinct http(s) link in the body, in order.
     *
     * @return list<string>
     */
    public function links(string $html): array
    {
        preg_match_all('/<a\s[^>]*href="(https?:\/\/[^"]+)"/i', $html, $matches);

        return array_values(array_unique(array_map(fn (string $url): string => html_entity_decode($url), $matches[1])));
    }

    // Saves the links of the campaign so clicks can be counted. Runs once when sending starts.
    public function storeLinks(Campaign $campaign): void
    {
        foreach ($this->links($this->body($campaign)) as $url) {
            $campaign->links()->firstOrCreate(['url' => $url]);
        }
    }

    // The full email for one subscriber. Without a delivery (test sends) nothing is tracked.
    public function email(Campaign $campaign, string $unsubscribeUrl, ?Delivery $delivery = null): string
    {
        $body = $this->body($campaign);

        if ($delivery !== null && $campaign->track_clicks) {
            $body = $this->trackLinks($body, $campaign, $delivery);
        }

        return view('newsletter::mail.campaign', [
            'campaign' => $campaign,
            'body' => $body,
            'unsubscribeUrl' => $unsubscribeUrl,
            'pixelUrl' => $delivery !== null && $campaign->track_opens ? route('newsletter.open', ['token' => $delivery->token]) : null,
        ])->render();
    }

    // Points every known link to the click counter, which then redirects.
    protected function trackLinks(string $html, Campaign $campaign, Delivery $delivery): string
    {
        $links = $campaign->links()->get()->keyBy(fn (CampaignLink $link): string => $link->url);

        return (string) preg_replace_callback('/(<a\s[^>]*href=")(https?:\/\/[^"]+)(")/i', function (array $match) use ($links, $delivery): string {
            $link = $links->get(html_entity_decode($match[2]));

            return $link === null ? $match[0] : $match[1].e(route('newsletter.click', ['token' => $delivery->token, 'link' => $link->id])).$match[3];
        }, $html);
    }
}
