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
        Schema::create('home_book_services', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('mobile_no');
            $table->string('neighborhood');
            $table->enum('gender', ['men', 'women']);
            $table->unsignedBigInteger('service_group_id');
            $table->unsignedBigInteger('service_id');
            $table->date('date');
            $table->string('time');
            $table->unsignedBigInteger('staff_id');
            $table->timestamps();

            // optional foreign keys
            $table->foreign('service_group_id');
            $table->foreign('service_id');
            $table->foreign('staff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_book_services');
    }
};
