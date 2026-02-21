<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->string('coupon_code')->nullable();
            $table->decimal('discount_amount', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            //
        });
    }
};
