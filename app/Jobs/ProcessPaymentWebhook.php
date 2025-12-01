<?php
namespace App\Jobs;

use Log;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Payments\GatewayManager;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $gateway;
    public string $payload;
    public array $headers;

    public function __construct(string $gateway, string $payload, array $headers = [])
    {
        $this->gateway = $gateway;
        $this->payload = $payload;
        $this->headers = $headers;
    }

    public function handle(GatewayManager $manager)
    {
        // Each gateway should implement a verifyWebhook + parseWebhookEvent method if necessary.
        $gatewayInstance = $manager->gateway($this->gateway);

        // Optional: verify signature (throw if invalid) - gateways expose their own helpers
        if (method_exists($gatewayInstance, 'verifyWebhook')) {
            $gatewayInstance->verifyWebhook($this->headers, $this->payload);
        }

        // Let the gateway parse the payload into a canonical shape:
        // ['type' => 'payment_intent.succeeded', 'm_payment_id' => '...', 'gateway_txn_id' => '...', 'amount' => 10.00, 'meta' => [...]]
        if (! method_exists($gatewayInstance, 'parseWebhook')) {
            // fallback: store raw payload
            \Log::info("Webhook received for {$this->gateway} (no parser)", json_decode($this->payload), true);
            return;
        }

        $data = $gatewayInstance->parseWebhook($this->payload);

        // Find payment by merchant reference OR gateway txn id
        $payment = null;
        if (! empty($data['m_payment_id'])) {
            $payment = Payment::where('m_payment_id', $data['m_payment_id'])->first();
        }
        if (! $payment && ! empty($data['gateway_txn_id'])) {
            $payment = Payment::where('gateway_txn_id', $data['gateway_txn_id'])->first();
        }

        // If no payment record: optionally create one (depends on your strategy)
        if (! $payment) {
            \Log::info('no payment found!');
            // create a minimal record to track the event
            $payment = Payment::create([
                'gateway' => $this->gateway,
                'm_payment_id' => $event['m_payment_id'] ?? null,
                'gateway_txn_id' => $event['gateway_txn_id'] ?? null,
                'amount' => $event['amount'] ?? 0,
                'currency' => $event['currency'] ?? 'ZAR',
                'status' => 'unknown',
                'gateway_response' => $event['meta'] ?? null,
            ]);
        }

        // Handle out of order processing.
        // Check event date.
        if ($data['timestamp'] < $payment->updated_at) {
            return;
        }

        // If event has already been processed, exit
        $procId = $data['provider_event_id'] ?? ($data['gateway_txn_id'] . ':' . ($data['event'] ?? 'unknown'));
        $existing = $payment->events()->where('processor_txn_id', $procId)->first();
        if ($existing) {
            // already processed
            return;
        }

        // Persist event & then apply state transitions
        $payment->addEvent($data['event'] ?? 'webhook', [
            'meta' => $data['meta'] ?? [],
            'raw' => $this->payload
        ], 'webhook', $procId);

        // Apply state changes
        switch ($data['event'] ?? '') {
            case 'payment_captured':
                $payment->update(['status' => 'captured']);
                break;
            case 'payment_failed':
                $payment->update(['status' => 'failed']);
                break;
            case 'payment_refunded':
                $payment->update(['status' => 'refunded']);
                break;
            case 'payment_authorized':
                $payment->update(['status' => 'authorised']);
                break;
            default:
                break;
        }
    }
}
