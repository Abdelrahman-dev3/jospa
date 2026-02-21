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
        Schema::create('booking_homes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_home_id');
            $table->foreignId('service_home_id');
            $table->string('name');
            $table->string('phone');
            $table->string('address');
            $table->date('date');
            $table->time('time');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'in-progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_homes');
    }
};
