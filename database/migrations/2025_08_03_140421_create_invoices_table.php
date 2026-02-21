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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('cart_ids'); // cart_ids IDs فقط بصيغة JSON
            $table->decimal('discount_amount', 10, 2)->default(0); // خصم الكوبونات
            $table->decimal('loyalty_points_discount', 10, 2)->default(0); // خصم الولاء
            $table->decimal('final_total', 10, 2); // المبلغ النهائي
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
