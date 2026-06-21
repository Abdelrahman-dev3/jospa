<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('coupon_discount_amount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('payment_gateway_discount_amount', 10, 2)->default(0)->after('coupon_discount_amount');
            $table->string('payment_gateway_discount_method')->nullable()->after('payment_gateway_discount_amount');
            $table->string('payment_gateway_discount_label')->nullable()->after('payment_gateway_discount_method');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'coupon_discount_amount',
                'payment_gateway_discount_amount',
                'payment_gateway_discount_method',
                'payment_gateway_discount_label',
            ]);
        });
    }
};
