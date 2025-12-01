<?php

namespace App\Services\Payments;

interface PaymentGatewayInterface {
    /**
     * Authorise a payment
     *
     * @param  array  $data
     * @return PaymentResult
     *
     * @throws PaymentException on failure
     */
    public function authorise(array $data): PaymentResult;

    /**
     * Capture a previously authorised payment.
     *
     * @param  string     $transactionId
     * @param  float|null $amount
     * @return PaymentResult
     *
     * @throws PaymentException on failure
     */
    public function capture(string $transactionId, ?float $amount = null): PaymentResult;

    /**
     * Void a payment
     *
     * @param  string  $transactionId
     * @return PaymentResult
     *
     * @throws PaymentException on failure
     */
    public function void(string $transactionId): PaymentResult;

    /**
     * Refund
     *
     * @param string $transactionId
     * @param float|null $amount If null, refund full amount
     * @return PaymentResult
     *
     * @throws PaymentException on failure
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult;
}
