<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessPaymentWebhook;

class WebhookController extends Controller
{
    /**
     * Receive raw webhook and dispatch job to handle it.
     * {gateway} allows a single controller for multiple gateway webhooks.
     */
    public function receive(Request $request, string $gateway)
    {
        // quick response to sender
        // Validate gateway exists in config
        if (! array_key_exists($gateway, config('payment.gateways', []))) {
            return response('Unknown gateway', 404);
        }

        $payload = $request->getContent(); // 
        $headers = $request->headers->all();

        // Dispatch a job to process it (idempotent, retriable)
        ProcessPaymentWebhook::dispatch($gateway, $payload, $headers);

        // Return 200 immediately
        return response('OK', 200);
    }
}
