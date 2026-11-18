<?php

namespace Verso\Plugins\Newsletter\Http\Controllers;

use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Verso\Plugins\Newsletter\Actions\Subscribe;
use Verso\Plugins\Newsletter\Models\CampaignLink;
use Verso\Plugins\Newsletter\Models\Delivery;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Models\Subscriber;

// Public endpoints: signup form, confirm link, unsubscribe link, open pixel and click counter.
class PublicController
{
    // A transparent 1x1 GIF for the open pixel.
    protected const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function subscribe(Request $request, Subscribe $subscribe): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'list' => ['nullable', 'string', 'max:190'],
            'website' => ['nullable', 'max:0'],
        ]);

        $list = isset($data['list']) ? MailingList::query()->where('slug', $data['list'])->first() : null;
        $subscribe->handle((string) $data['email'], isset($data['name']) ? (string) $data['name'] : null, $list);

        // The same answer for new and known addresses, so nobody can test who is subscribed.
        return back()->with('newsletter', __('newsletter::Almost done. Please check your inbox and click the link to confirm.'));
    }

    public function confirm(Subscriber $subscriber): Response
    {
        $subscriber->confirm();

        return $this->message(__('newsletter::Subscription confirmed'), __('newsletter::Thank you. You will get the next issue in your inbox.'));
    }

    public function unsubscribe(Subscriber $subscriber): Response
    {
        $subscriber->unsubscribe();

        return $this->message(__('newsletter::You are unsubscribed'), __('newsletter::You will not get more newsletters from us. You can sign up again at any time.'));
    }

    public function open(string $token): Response
    {
        Delivery::query()->where('token', $token)->first()?->markOpened();

        return response((string) base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    // Only links saved for the campaign of this delivery are followed, so this is not an open redirect.
    public function click(string $token, int $link): RedirectResponse
    {
        $delivery = Delivery::query()->where('token', $token)->firstOrFail();
        $target = CampaignLink::query()->where('campaign_id', $delivery->campaign_id)->findOrFail($link);

        $delivery->markClicked();
        $target->increment('clicks');

        return redirect()->away($target->url);
    }

    // A short page in the active theme layout. It is never cached, because it belongs to one subscriber.
    protected function message(string $title, string $message): Response
    {
        return app(ThemeResponse::class)
            ->view('newsletter.message', ['title' => $title, 'message' => $message])
            ->header('Cache-Control', 'no-store, private');
    }
}
