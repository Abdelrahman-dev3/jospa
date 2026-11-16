<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional booking window per service. When set it overrides the category window.
     * Both null = fall back to the category window (if any).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'booking_start_time')) {
                $table->time('booking_start_time')->nullable();
            }
            if (! Schema::hasColumn('services', 'booking_end_time')) {
                $table->time('booking_end_time')->nullable()->after('booking_start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            foreach (['booking_end_time', 'booking_start_time'] as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
