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
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'show_in_online_booking')) {
                $table->boolean('show_in_online_booking')->default(1)->after('is_visible');
            }

            if (! Schema::hasColumn('services', 'show_in_gift_card')) {
                $table->boolean('show_in_gift_card')->default(1)->after('show_in_online_booking');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'show_in_gift_card')) {
                $table->dropColumn('show_in_gift_card');
            }

            if (Schema::hasColumn('services', 'show_in_online_booking')) {
                $table->dropColumn('show_in_online_booking');
            }
        });
    }
};
