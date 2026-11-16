<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional booking window per category (e.g. massage bookable only at night).
     * Both null = no restriction beyond staff/branch working hours.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'booking_start_time')) {
                $table->time('booking_start_time')->nullable()->after('calendar_color');
            }
            if (! Schema::hasColumn('categories', 'booking_end_time')) {
                $table->time('booking_end_time')->nullable()->after('booking_start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            foreach (['booking_end_time', 'booking_start_time'] as $column) {
                if (Schema::hasColumn('categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
