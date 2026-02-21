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
        Schema::create('ouroffersections', function (Blueprint $table) {
            $table->id();
            $table->string('discount_type'); // نوع الخصم (نسبة % أو مبلغ)
            $table->decimal('discount_value', 8, 2); // قيمة الخصم
            $table->json('description')->nullable(); // وصف متعدد اللغات (ar/en)
            $table->date('start_date'); // تاريخ البداية
            $table->date('end_date');   // تاريخ النهاية
            $table->string('image')->nullable(); // صورة العرض
            $table->string('color', 50)->nullable();
            $table->boolean('overlay')->default(0);
            $table->string('link')->nullable();  // رابط العرض
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ouroffersections');
    }
};
