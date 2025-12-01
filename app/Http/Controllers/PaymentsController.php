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

class PaymentsController extends Controller {

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



        } catch(Exception $e) {
            return response()->json([
                "error" => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'payment_id' => $updated->id,
            'status' => $updated->status,
            'refund_meta' => $updated->gateway_response
        ]);
    }

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
