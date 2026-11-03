<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_reviews', function (Blueprint $table) {
            $table->timestamp('google_maps_clicked_at')->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_reviews', function (Blueprint $table) {
            $table->dropColumn('google_maps_clicked_at');
        });
    }
};
