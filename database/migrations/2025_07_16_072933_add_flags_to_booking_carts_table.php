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
        Schema::table('booking_carts', function (Blueprint $table) {
            $table->boolean('agreed')->default(false); 
            $table->boolean('auto_change_staff')->default(false); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_carts', function (Blueprint $table) {
            //
        });
    }
};
