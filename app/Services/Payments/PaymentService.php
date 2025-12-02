<?php
namespace App\Services\Payments;

use Exception;
use App\Models\Refund;
use App\Models\Payment;
use Illuminate\Support\Str;
use App\Support\RetryHelper;
use Illuminate\Support\Facades\DB;
use App\Support\IdempotencyInterface;
use Illuminate\Http\Client\RequestException;

class PaymentService
{
    protected GatewayManager $gatewayManager;
    protected IdempotencyInterface $idempotencyInterface;

    public function __construct(
        GatewayManager $gatewayManager,
        IdempotencyInterface $idempotencyInterface
    )
    {
        $this->gatewayManager = $gatewayManager;
        $this->idempotencyInterface = $idempotencyInterface;
    }

    // Different strategies based on type of exception
    protected function retryStrategies(): array
    {
        return [
            [
                'exception' => \Illuminate\Http\Client\ConnectionException::class,
                'type' => 'exponential',
                'base_delay_ms' => 300,
                'max_attempts' => 4,
            ],
            [
                'exception' => RequestException::class,
                'status_codes' => [429],
                'type' => 'capped_exponential',
                'base_delay_ms' => 500,
                'max_delay_ms' => 8000,
                'max_attempts' => 5,
            ],
            [
                'exception' => RequestException::class,
                'status_codes' => [500, 502, 503, 504],
                'type' => 'jitter',
                'base_delay_ms'=> 400,
                'max_attempts' => 3,
            ],
        ];
    }


    /**
     * Create payment record and call gateway->authorise.
     *
     */
    public function createAndAuthorise(
        string $gatewayName,
        float $amount,
        string $currency,
        string $token
    ): Payment
    {
        $payment = Payment::create([
            'gateway' => $gatewayName,
            'amount' => $amount,
            'currency' => $currency ?? 'ZAR',
            'status' => 'pending',
        ]);

        $gateway = $this->gatewayManager->gateway($gatewayName);
        $key = 'auth_' . $payment->id;


        $result = $this->idempotencyInterface->run(
            function () use ($gateway, $amount, $token, $currency, $payment) {
                return RetryHelper::run(
                    function ($attempt) use ($gateway, $amount, $token, $currency) {
                        return $gateway->authorise(array_merge([
                            'amount' => $amount,
                            'token' => $token
                        ]));
                    },
                    $this->retryStrategies(),
                    $payment->id,
                    'authorise'
                );
            },
            $key,
            'authorise'
        );

        // Save gateway result & txn id
        $payment->update([
            'gateway_txn_id' => $result->transactionId ?: $payment->gateway_txn_id,
            'gateway_response' => $result->meta,
        ]);

        $payment->addEvent('authorise_attempt', [
            'result' => $result->meta,
            'message' => $result->message,
        ], 'gateway');

        // If success -> set status 'authorised' (some gateways dont separate authorise/capture)
        if ($result->success) {
            $payment->update(['status' => 'authorised']);
        } else {
            $payment->update(['status' => 'failed']);
        }

        return $payment;
    }

    /**
     * Capture a payment (id is internal payment id)
     */
    public function capture(Payment $payment, ?float $amount = null): Payment
    {
        // Before anything, ensure payment can be captured.
        $payment->ensureCanCapture();

        $gateway = $this->gatewayManager->gateway($payment->gateway);
        $key = 'capture_' . $payment->id;

        $result = $this->idempotencyInterface->run(
            function () use ($payment, $gateway, $amount) {
                return RetryHelper::run(
                    function ($attempt) use ($gateway, $payment) {
                        return $gateway->capture(
                            $payment->id,
                            $payment->amount
                        );
                    },
                    $this->retryStrategies(),
                    $payment->id
                );
            },
            $key,
            'capture'
        );

        $payment->update([
            'status' => $result->success ? 'captured' : $payment->status,
        ]);

        $payment->addEvent('capture_attempt', ['result' => $result->meta], 'gateway', $result->transactionId);

        return $payment;
    }

    /**
     * Void payment
     *
     * @param Payment $payment
     * @return void
     */
    public function void(Payment $payment)
    {
        // Ensure payment is in correct state to be voided.
        $payment->ensureCanVoid();

        $gateway = $this->gatewayManager->gateway($payment->gateway);
        $key = 'capture_' . $payment->id;

        $result = $this->idempotencyInterface->run(
            function () use ($gateway, $payment) {
                return RetryHelper::run(
                    function ($attempt) use ($gateway, $payment) {
                        return $gateway->void(
                            $payment->id,
                            $payment->amount
                        );
                    },
                    $this->retryStrategies(),
                    $payment->id
                );
            },
            $key,
            'void'
        );

        $payment->update([
            'gateway_response' => $result->meta,
            'status' => $result->success ? 'voided' : $payment->status,
        ]);

        $payment->addEvent('refund_attempt', ['result' => $result->meta], 'gateway', $result->transactionId);

        return $payment;
    }

    /**
     * Refund
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): Payment
    {
        // First ensure payment is in the correct state to be refunded.
        $payment->ensureCanRefund($amount);

        $gateway = $this->gatewayManager->gateway($payment->gateway);
        $key = 'refund_' . $payment->id . '_' . Str::uuid();

        if ($payment->is_fully_refunded) {
            throw new Exception("Payment has already been fully refunded.");
        }

        if ($amount > $payment->remaining_amount) {
            throw new Exception("Refund exceeds remaining refundable amount.");
        }

        $result = $this->idempotencyInterface->run(
            function () use ($gateway, $payment, $amount) {
                return RetryHelper::run(
                    function ($attempt) use ($gateway, $payment, $amount) {
                        return $gateway->refund($payment->gateway_txn_id, $amount);
                    },
                    $this->retryStrategies(),
                    $payment->id
                );
            },
            $key,
            'refund'
        );

        $payment->update([
            'gateway_response' => $result->meta,
            'status' => $result->success ? 'refunded' : $payment->status,
        ]);

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'amount' => $amount,
            'reason'  => $reason,
            'status'   => $result->status ?? 'pending',
            'gateway_refund_id' => $result->transactionId,
        ]);

        $payment->addEvent('refund_attempt', ['result' => $result->meta], 'gateway', $result->transactionId);

        return $payment;
    }
}
