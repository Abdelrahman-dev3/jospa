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
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->boolean('show_sender_name')->default(true)->after('message');
            $table->boolean('show_recipient_name')->default(true)->after('show_sender_name');
            $table->boolean('show_sender_phone')->default(false)->after('show_recipient_name');
            $table->boolean('show_recipient_phone')->default(false)->after('show_sender_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn([
                'show_sender_name',
                'show_recipient_name',
                'show_sender_phone',
                'show_recipient_phone',
            ]);
        });
    }
};
