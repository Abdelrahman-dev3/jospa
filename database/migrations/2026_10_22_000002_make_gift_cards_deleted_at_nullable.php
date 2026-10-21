<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some environments have gift_cards.deleted_at as NOT NULL without a default,
     * which makes every insert fail ("Field 'deleted_at' doesn't have a default value").
     */
    public function up(): void
    {
        if (! Schema::hasTable('gift_cards') || DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('gift_cards', 'deleted_at')) {
            Schema::table('gift_cards', function ($table) {
                $table->softDeletes();
            });

            return;
        }

        DB::statement('ALTER TABLE `gift_cards` MODIFY `deleted_at` TIMESTAMP NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Intentionally left empty: restoring a NOT NULL column would re-break inserts.
    }
};
