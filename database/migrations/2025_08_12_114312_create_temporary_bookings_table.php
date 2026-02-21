<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('temporary_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('guest_token')->unique();
            $table->json('booking_data');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('temporary_bookings');
    }
};
