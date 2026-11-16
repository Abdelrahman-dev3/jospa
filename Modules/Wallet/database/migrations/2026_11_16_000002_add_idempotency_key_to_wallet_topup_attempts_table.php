<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency-Key sent by the app on POST /api/wallet/topup/init.
     * Unique per user: a retried init with the same key returns the same attempt.
     */
    public function up(): void
    {
        Schema::table('wallet_topup_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_topup_attempts', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('user_id');
                $table->unique(['user_id', 'idempotency_key'], 'wallet_topup_attempts_user_idem_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_topup_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_topup_attempts', 'idempotency_key')) {
                $table->dropUnique('wallet_topup_attempts_user_idem_unique');
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
