<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions_coupon', function (Blueprint $table) {
            $table->json('specific_dates')->nullable()->after('services');
        });
    }

    public function down(): void
    {
        Schema::table('promotions_coupon', function (Blueprint $table) {
            $table->dropColumn('specific_dates');
        });
    }
};
