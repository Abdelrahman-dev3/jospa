<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 50)->index();
            $table->string('payment_method', 50)->nullable()->index();
            $table->string('page', 30)->nullable()->index();
            $table->string('status', 30)->default('initiated')->index();
            $table->string('currency', 10)->default('SAR');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->json('cart_ids')->nullable();
            $table->json('gift_ids')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('gift_code')->nullable();
            $table->boolean('wallet_used')->default(false);
            $table->boolean('loyalty_used')->default(false);
            $table->string('merchant_reference')->nullable()->index();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('gateway_checkout_id')->nullable()->index();
            $table->string('gateway_order_id')->nullable()->index();
            $table->json('gateway_response')->nullable();
            $table->json('callback_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
