<?php

namespace Verso\Plugins\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Verso\Plugins\Shop\Payments\Stripe\StripeSignature;
use Verso\Plugins\Shop\Payments\Stripe\StripeWebhook;
use Verso\Plugins\Shop\Support\ShopSettings;

// Receives Stripe events. Only events with a valid signature are used.
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, ShopSettings $settings, StripeSignature $signature, StripeWebhook $webhook): JsonResponse
    {
        $secret = $settings->stripeWebhookSecret();
        $payload = $request->getContent();

        if ($secret === null || ! $signature->isValid($payload, $request->header('Stripe-Signature'), $secret)) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        return response()->json(['result' => $webhook->handle($event)]);
    }
}
