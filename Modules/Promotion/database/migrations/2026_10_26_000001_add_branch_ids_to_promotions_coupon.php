<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions_coupon', function (Blueprint $table) {
            // null / empty = valid in every branch
            $table->json('branch_ids')->nullable()->after('specific_dates');
        });
    }

    public function down(): void
    {
        Schema::table('promotions_coupon', function (Blueprint $table) {
            $table->dropColumn('branch_ids');
        });
    }
};
