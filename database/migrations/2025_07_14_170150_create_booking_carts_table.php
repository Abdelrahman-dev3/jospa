<?php

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
        Schema::create('booking_carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('mobile_no');
            $table->string('neighborhood')->nullable();
            $table->string('branch');
            $table->string('gender'); 
            $table->unsignedBigInteger('service_group_id');
            $table->unsignedBigInteger('service_id');
            $table->date('date')->nullable();
            $table->string('time')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_carts');
    }
};
