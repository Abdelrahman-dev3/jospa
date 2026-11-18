<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tracks sending paid payment requests to Odoo (event odoo_create_payment_invoice). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            // null (not due) | pending | sending | synced | failed
            $table->string('odoo_sync_status', 20)->nullable()->index()->after('paid_at');
            $table->string('odoo_invoice_number')->nullable()->after('odoo_sync_status');
            // Private storage path (storage/app) or an https URL returned by Odoo.
            $table->string('odoo_invoice_pdf')->nullable()->after('odoo_invoice_number');
            $table->unsignedSmallInteger('odoo_sync_attempts')->default(0)->after('odoo_invoice_pdf');
            $table->string('odoo_last_error')->nullable()->after('odoo_sync_attempts');
            $table->timestamp('odoo_last_attempt_at')->nullable()->after('odoo_last_error');
            $table->timestamp('odoo_synced_at')->nullable()->after('odoo_last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn([
                'odoo_sync_status',
                'odoo_invoice_number',
                'odoo_invoice_pdf',
                'odoo_sync_attempts',
                'odoo_last_error',
                'odoo_last_attempt_at',
                'odoo_synced_at',
            ]);
        });
    }
};
