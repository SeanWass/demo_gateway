<?php
return [
    'default_gateway' => env('PAYMENT_DEFAULT', 'example'),
    'gateways' => [
        'example' => \App\Services\Payments\ExampleGateway::class,
    ],
];
