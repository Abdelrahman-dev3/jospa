<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('booking_payment_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('token', 64)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('SAR');
            // pending | paid | cancelled | expired
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('payment_attempt_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_payment_links');
    }
};
