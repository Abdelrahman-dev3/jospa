<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('gift_page_enabled')->default(false);
            $table->string('gift_slug')->nullable()->unique();
            $table->json('gift_card_amounts')->nullable();
            $table->decimal('gift_card_min_amount', 10, 2)->default(50);
        });

        Schema::create('gift_bouquets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->string('badge')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('gift_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('bouquet_id')->nullable();
            $table->string('bouquet_name')->nullable();
            $table->decimal('bouquet_price', 10, 2)->default(0);
            $table->decimal('card_amount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'bouquet_id', 'bouquet_name', 'bouquet_price', 'card_amount']);
        });

        Schema::dropIfExists('gift_bouquets');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['gift_slug']);
            $table->dropColumn(['gift_page_enabled', 'gift_slug', 'gift_card_amounts', 'gift_card_min_amount']);
        });
    }
};
