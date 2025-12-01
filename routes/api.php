<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\WebhookController;

Route::post('/payments', [PaymentsController::class, 'authorise']);
Route::get('/payments/{payment}', [PaymentsController::class, 'getPayment']);
Route::post('payments/{payment}/capture', [PaymentsController::class, 'capture']);
Route::post('payments/{payment}/void', [PaymentsController::class, 'void']);
Route::post('payments/{payment}/refund', [PaymentsController::class, 'refund']);
Route::post('webhooks/payment-status/{gateway}', [WebhookController::class, 'receive']);
