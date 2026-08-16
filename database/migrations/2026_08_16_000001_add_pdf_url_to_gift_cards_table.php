<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gift_cards') || Schema::hasColumn('gift_cards', 'pdf_url')) {
            return;
        }

        Schema::table('gift_cards', function (Blueprint $table) {
            $table->text('pdf_url')->nullable()->after('balance');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gift_cards') || ! Schema::hasColumn('gift_cards', 'pdf_url')) {
            return;
        }

        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn('pdf_url');
        });
    }
};
