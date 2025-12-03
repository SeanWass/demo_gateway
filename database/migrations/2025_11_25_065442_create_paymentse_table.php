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
            $table->string('gateway')->index();
            $table->string('gateway_txn_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('ZAR');
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->json('gateway_response')->nullable();
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
