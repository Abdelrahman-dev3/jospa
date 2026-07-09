<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gift_cards')) {
            Schema::create('gift_cards', function (Blueprint $table) {
                $table->id();
                $table->string('ref')->nullable();
                $table->decimal('balance', 10, 2)->default(0);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('delivery_method')->nullable();
                $table->string('sender_name')->nullable();
                $table->string('recipient_name')->nullable();
                $table->string('sender_phone')->nullable();
                $table->string('recipient_phone')->nullable();
                $table->string('message', 100)->nullable();
                $table->json('requested_services')->nullable();
                $table->json('package_ids')->nullable();
                $table->json('coupons')->nullable();
                $table->decimal('options_amount', 10, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->tinyInteger('payment_status')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('code')->unique()->nullable();
                $table->decimal('commission_rate', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('staff_working_hours')) {
            Schema::create('staff_working_hours', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id');
                $table->string('day')->nullable();
                $table->string('start_time')->nullable();
                $table->string('end_time')->nullable();
                $table->tinyInteger('is_holiday')->nullable();
                $table->longText('breaks')->nullable()->default('[]');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_working_hours');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('gift_cards');
    }
};
