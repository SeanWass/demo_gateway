<?php
namespace App\Services\Payments;

use App\Services\Payments\PaymentResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ExampleGateway extends AbstractPaymentGateway
{
    public function __construct(\Illuminate\Http\Client\Factory $http)
    {
        parent::__construct($http);
    }

    public function authorise(array $data): PaymentResult
    {
        try {
            // Faking the response
            Http::fake([
                'https://www.gateway.com/*' => function ($request) use ($data) {

                $amount = $data['amount'];

                    // Case 1: Amount ends in 99 cents → fail immediately
                    if (self::amountEnds99($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "failed"
                        ], 429);
                    }

                    // Case 2: Amount ends in 77 cents → retryable failure
                    if (self::amountEnds77($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "pending"
                        ], 502);
                    }

                    // Default success response
                    return Http::response([
                        "status" => "success",
                        "transaction_id" => "txn_abc123",
                        "authorization_code" => "auth_xyz789",
                        "state" => "authorised"
                    ], 200);
                },
            ]);

            $response = Http::post('https://www.gateway.com/authorise', [
                'amount' => $data['amount'],
            ])->throw();

            return PaymentResult::success(
                $response['transaction_id'] ?? '',
                $body['amount'] ?? ($data['amount'] ?? null),
                $response->json(),
                $body['message'] ?? null
            );
        } catch (RequestException $e) {
            $this->handleError(
                $e->response,
                'Authorise failed: '.$e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        try {
            // Faking the response
            Http::fake([
                'https://www.gateway.com/*' => function ($request) use ($amount) {

                    // Case 1: Amount ends in 99 cents → fail immediately
                    if (self::amountEnds99($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "failed"
                        ], 429);
                    }

                    // Case 2: Amount ends in 77 cents → retryable failure
                    if (self::amountEnds77($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "pending"
                        ], 502);
                    }

                    // Default success response
                    return Http::response([
                        "status" => "success",
                        "transaction_id" => "txn_abc123",
                        "authorization_code" => "auth_xyz789",
                        "state" => "captured"
                    ], 200);
                },
            ]);

            $response = Http::post('https://www.gateway.com/capture', [
                'amount' => $amount,
            ])->throw();

            return PaymentResult::success(
                $response['transaction_id'] ?? $transactionId,
                $response['amount'] ?? $amount,
                $response->json(),
                $response['message'] ?? null
            );
        } catch (RequestException $e) {
            $this->handleError(
                $e->response,
                'Capture failed: '.$e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    public function void(string $transactionId, ?float $amount = null): PaymentResult
    {
        try {
            // Faking the response
            Http::fake([
                'https://www.gateway.com/*' => function ($request) use ($amount) {

                    // Case 1: Amount ends in 99 cents → fail immediately
                    if (self::amountEnds99($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "failed"
                        ], 429);
                    }

                    // Case 2: Amount ends in 77 cents → retryable failure
                    if (self::amountEnds77($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "pending"
                        ], 502);
                    }

                    // Default success response
                    return Http::response([
                        "status" => "success",
                        "transaction_id" => "txn_abc123",
                        "authorization_code" => "auth_xyz789",
                        "state" => "voided"
                    ], 200);
                },
            ]);

            $response = Http::post('https://www.gateway.com/void', [
                'amount' => $amount,
            ])->throw();

            return PaymentResult::success(
                $response['transaction_id'] ?? $transactionId,
                $response['amount'] ?? null,
                $response->json(),
                $response['message'] ?? null
            );
        } catch (RequestException $e) {
            $this->handleError(
                $e->response,
                'Void failed: '.$e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    public function refund(string $transactionId, ?float $amount = null): PaymentResult
    {
        try {
            // Faking the response
            Http::fake([
                'https://www.gateway.com/*' => function ($request) use ($amount) {

                    // Case 1: Amount ends in 99 cents → fail immediately
                    if (self::amountEnds99($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "failed"
                        ], 429);
                    }

                    // Case 2: Amount ends in 77 cents → retryable failure
                    if (self::amountEnds77($amount)) {
                        return Http::response([
                            "status" => "failed",
                            "transaction_id" => "txn_abc123",
                            "authorization_code" => "auth_xyz789",
                            "state" => "pending"
                        ], 502);
                    }

                    // Default success response
                    return Http::response([
                        "status" => "success",
                        "transaction_id" => "txn_abc123",
                        "authorization_code" => "auth_xyz789",
                        "state" => "refunded"
                    ], 200);
                },
            ]);

            $response = Http::post('https://www.gateway.com/refund', [
                'amount' => $amount,
            ])->throw();

            return PaymentResult::success(
                $response['refund_id'] ?? $transactionId,
                $response['amount'] ?? $amount,
                $response->json(),
                $response['message'] ?? null
            );
        } catch (RequestException $e) {
            $this->handleError(
                $e->response,
                'Refund failed: '.$e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    public function verifyWebhook(array $headers, string $payload) : array
    {
        $secret = env("PAYMENT_EXAMPLE_SECRET");
        $signature = $headers['x-signature'][0] ?? null;  // Need to get index 0 as laravel stores headers as arrays in case of multiple values
        $expected_signature = hash_hmac('sha256', $payload, $secret);

        // \Log::info($expected_signature);

        if (!$signature) {
            \Log::error("Signature does not exist");
            abort(403, 'Signature does not exist');
        }

        if (! hash_equals($expected_signature, $signature)) {
            \Log::error("Invalid Signature");
            abort(403, 'Invalid Signature.');
        }

        return json_decode($payload, true);
    }

    public function parseWebhook(string $payload): array
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid webhook JSON");
        }

        return [
            'gateway_txn_id' => $data['transaction_id'],
            'event' => $data['event'],
            'amount' => $data['amount'] / 100,
            'timestamp' => $data['timestamp'],
        ];
    }

    // The following functions would need to be removed as they are only being used for faking responses depending on the
    // amount that is being sent through.
    static private function amountEnds99(float $amount) : bool
    {
        $str = number_format((float)$amount, 2, '.', '');
        return str_ends_with($str, '.99');
    }

    static private function amountEnds77(float $amount) : bool
    {
        $str = number_format((float) $amount, 2, '.', '');
        return str_ends_with($str, '.77');
    }
}
