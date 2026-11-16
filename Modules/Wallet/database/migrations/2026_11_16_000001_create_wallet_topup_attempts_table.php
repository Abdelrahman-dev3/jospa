<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topup_attempts', function (Blueprint $table) {
            $table->id();
            // Public identifier given to the mobile app (never the numeric id).
            $table->uuid('attempt_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('gateway', 20)->default('hyperpay');
            $table->string('brand', 20)->default('VISA');
            // pending | paid | failed | cancelled | expired
            $table->string('status', 20)->default('pending')->index();
            $table->string('merchant_transaction_id', 32)->unique();
            $table->string('checkout_id')->nullable()->index();
            // Unique: one gateway payment can credit the wallet only once.
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->string('result_code', 32)->nullable();
            $table->string('result_description')->nullable();
            $table->unsignedBigInteger('wallet_history_id')->nullable();
            $table->text('gateway_response')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topup_attempts');
    }
};
