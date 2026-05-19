<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('javna_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_scope')->nullable()->index();
            $table->string('event')->nullable()->index();
            $table->string('account_id')->nullable()->index();
            $table->string('message_id')->nullable()->index();
            $table->string('from_number')->nullable()->index();
            $table->string('to_number')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('javna_webhook_events');
    }
};
