<?php

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->index(); // future proofing, ie allowing multiple gateways
            $table->string('gateway_txn_id')->nullable()->index(); // gateway paymentIntent / pf_payment_id
            $table->string('m_payment_id')->nullable()->index(); // merchant reference
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('ZAR');
            $table->string('status')->default('pending')->index(); // different statuses of a payment
            $table->json('metadata')->nullable();
            $table->json('gateway_response')->nullable(); // last gateway response
            $table->string('idempotency_key')->nullable()->index();
            $table->timestamps();
            $table->SoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
