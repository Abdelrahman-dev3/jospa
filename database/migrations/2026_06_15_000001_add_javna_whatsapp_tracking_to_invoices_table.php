<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('javna_whatsapp_message_id')->nullable()->index()->after('final_total');
            $table->string('javna_whatsapp_status')->nullable()->index()->after('javna_whatsapp_message_id');
            $table->string('javna_whatsapp_payload_style')->nullable()->after('javna_whatsapp_status');
            $table->timestamp('javna_whatsapp_sent_at')->nullable()->after('javna_whatsapp_payload_style');
            $table->timestamp('javna_whatsapp_last_event_at')->nullable()->after('javna_whatsapp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'javna_whatsapp_message_id',
                'javna_whatsapp_status',
                'javna_whatsapp_payload_style',
                'javna_whatsapp_sent_at',
                'javna_whatsapp_last_event_at',
            ]);
        });
    }
};
