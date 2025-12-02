<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentGatewayInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HTTPResponse;
use OpenApi\Annotations as OA;
/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Payment Gateway API",
 *      description="API documentation for payment gateway API"
 * )
 */
class PaymentsController extends Controller {
    /**
     * Authorise a payment
     *
     * @param Request $request
     * @param PaymentService $service
     * @return void
     *
     * * @OA\Post(
     *     path="/api/payments/authorise",
     *     summary="Authorise a payment",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount","currency","token","gateway"},
     *             @OA\Property(property="gateway", type="string", example="stripe"),
     *             @OA\Property(property="amount", type="number", example=199.99),
     *             @OA\Property(property="currency", type="string", example="ZAR"),
     *             @OA\Property(property="token", type="string", example="tok_123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment authorised",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="status", type="string", example="authorised")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Invalid request")
     * )
     */
    public function authorise(Request $request, PaymentService $service)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
            'gateway' => 'required|string',
        ]);

        try {
            $payment = $service->createAndAuthorise(
                $data['gateway'],
                (float)$data['amount'],
                $data['currency'],
                $data['token']
            );

            return response()->json([
                'status' => $payment->status,
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway_meta' => $payment->gateway_response,
                'transaction_id' => $payment->gateway_txn_id,
            ]);
        } catch (Exception $e) {
            return response()->json([
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Capture a payment
     *
     * @param Payment $payment
     * @param Request $request
     * @param PaymentService $service
     * @return void
    * * @OA\Post(
    *       path="/api/payments/{payment_id}/capture",
    *       summary="Capture a payment",
    *       description="Captures a payment using the PaymentService. Optionally, you can specify an amount to capture.",
    *       tags={"Payments"},
    *       @OA\Parameter(
    *           name="payment_id",
    *           in="path",
    *           required=true,
    *           description="ID of the payment to capture",
    *           @OA\Schema(type="integer")
    *       ),
    *       @OA\RequestBody(
    *           required=false,
    *           description="Optional payload with amount to capture",
    *           @OA\JsonContent(
    *               @OA\Property(property="amount", type="number", format="float", example=100.50)
    *           )
    *       ),
    *       @OA\Response(
    *           response=200,
    *           description="Payment captured successfully",
    *           @OA\JsonContent(
    *               @OA\Property(property="payment_id", type="integer", example=123),
    *               @OA\Property(property="status", type="string", example="captured"),
    *               @OA\Property(property="transaction_id", type="string", example="txn_abc123"),
    *               @OA\Property(property="gateway_meta", type="object", example={"response_code": "00", "message": "Success"})
    *           )
    *       ),
    *       @OA\Response(
    *           response=500,
    *           description="Internal server error",
    *           @OA\JsonContent(
    *               @OA\Property(property="error", type="string", example="Some error message")
    *           )
    *       )
    *   )
*/
    public function capture(Payment $payment, Request $request, PaymentService $service)
    {
        try {
            $updated = $service->capture(
                $payment,
                $request->amount ? (float) $request->amount : null
            );

            return response()->json([
                'payment_id' => $updated->id,
                'status' => $updated->status,
                'transaction_id' => $updated->gateway_txn_id,
                'gateway_meta' => $updated->gateway_response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void a payment
     *
     * @param Payment $payment
     * @param PaymentService $service
     * @return void
     * @OA\Post(
     *      path="/api/payments/{payment_id}/void",
     *      summary="Void a payment",
     *      description="Voids an existing payment using the PaymentService.",
     *      tags={"Payments"},
     *      @OA\Parameter(
     *          name="payment_id",
     *          in="path",
     *          required=true,
     *          description="ID of the payment to void",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Payment voided successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="payment_id", type="integer", example=123),
     *              @OA\Property(property="status", type="string", example="voided")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Some error message")
     *          )
     *      )
     * )
     */
    public function void(Payment $payment, PaymentService $service)
    {
        try {
            $updated = $service->void($payment);

            return response()->json([
                'payment_id' => $updated->id,
                'status' => $updated->status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund a payment
     *
     * @param Payment $payment
     * @param Request $request
     * @param PaymentService $service
     * @return void
     * @OA\Post(
     *      path="/api/payments/{payment_id}/refund",
     *      summary="Refund a payment",
     *      description="Refunds a payment partially or fully using the PaymentService.",
     *      tags={"Payments"},
     *      @OA\Parameter(
     *          name="payment_id",
     *          in="path",
     *          required=true,
     *          description="ID of the payment to refund",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Refund details",
     *          @OA\JsonContent(
     *              required={"amount","reason"},
     *              @OA\Property(property="amount", type="number", format="float", example=50.00, description="Amount to refund"),
     *              @OA\Property(property="reason", type="string", example="Customer requested refund", description="Reason for refund")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Payment refunded successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="payment_id", type="integer", example=123),
     *              @OA\Property(property="status", type="string", example="refunded"),
     *              @OA\Property(property="refund_meta", type="object", example={"response_code": "00", "message": "Refund successful"})
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Some error message")
     *          )
     *      )
     *  )
     */
    public function refund(Payment $payment, Request $request, PaymentService $service)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string'
        ]);

        try {
            $updated = $service->refund(
                $payment,
                $request->amount ? (float)$request->amount : null, // If no amount is set, then do a full refund
                $request->reason
            );

            return response()->json([
                'payment_id' => $updated->id,
                'status' => $updated->status,
                'refund_meta' => $updated->gateway_response
            ]);
        } catch(Exception $e) {
            return response()->json([
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Returns a payment, with history and refunds(if there are refunds)
     *
     * @param Payment $payment
     * @return void
     * @OA\Get(
     *      path="/api/payments/{payment_id}",
     *      summary="Get payment details",
     *      description="Retrieve detailed information about a payment, including its refunds and event history.",
     *      tags={"Payments"},
     *      @OA\Parameter(
     *          name="payment_id",
     *          in="path",
     *          required=true,
     *          description="ID of the payment to retrieve",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Payment retrieved successfully",
     *              @OA\JsonContent(
     *              @OA\Property(
     *                  property="payment",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=123),
     *                  @OA\Property(property="amount", type="number", format="float", example=100.50),
     *                  @OA\Property(property="currency", type="string", example="USD"),
     *                  @OA\Property(property="status", type="string", example="captured"),
     *                  @OA\Property(property="refund_status", type="string", example="partial"),
     *                  @OA\Property(property="gateway", type="string", example="stripe"),
     *                  @OA\Property(property="gateway_txn_id", type="string", example="txn_abc123"),
     *                  @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02T12:00:00Z"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02T12:10:00Z")
     *             ),
     *             @OA\Property(
     *                  property="refunds",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="amount", type="number", format="float", example=50.00),
     *                      @OA\Property(property="status", type="string", example="refunded"),
     *                      @OA\Property(property="reason", type="string", example="Customer requested refund"),
     *                      @OA\Property(property="gateway_refund_id", type="string", example="refund_abc123"),
     *                      @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02T12:05:00Z")
     *                  )
     *             ),
     *             @OA\Property(
     *                 property="history",
     *                  type="array",
     *                  @OA\Items(
     *                        type="object",
     *                        @OA\Property(property="type", type="string", example="status_change"),
     *                       @OA\Property(property="message", type="string", example="Payment captured successfully"),
     *                        @OA\Property(property="data", type="object", example={"old_status":"pending","new_status":"captured"}),
     *                       @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02T12:03:00Z")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Some error message")
     *          )
     *      )
     *  )
     */
    public function getPayment(Payment $payment) {
        try {
            $payment->load([
                'refunds',
                'events',
            ]);
            return response()->json([
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'refund_status' => $payment->refund_status,
                'gateway' => $payment->gateway,
                'gateway_txn_id'=> $payment->gateway_txn_id,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ],
            'refunds' => $payment->refunds->map(function ($r) {
                return [
                    'id' => $r->id,
                    'amount' => $r->amount,
                    'status' => $r->status,
                    'reason' => $r->reason,
                    'gateway_refund_id' => $r->gateway_refund_id,
                    'created_at'=> $r->created_at,
                ];
            }),
            'history' => $payment->events->map(function ($h) {
                return [
                    'type' => $h->type,
                    'message' => $h->message,
                    'data' => $h->data,
                    'created_at' => $h->created_at,
                ];
            }),
        ]);

        } catch(Exception $e) {
            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        };
    }
}
