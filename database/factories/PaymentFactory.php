<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = \App\Models\Payment::class;

    public function definition()
    {
        return [
            'gateway' => 'example',
            'amount' => 100.00,
            'currency' => 'ZAR',
            'status' => 'pending',
            'gateway_txn_id' => null,
            'gateway_response' => null,
        ];
    }
}
