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
        if (! Schema::hasTable('occasions')) {
            Schema::create('occasions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('occasion_date')->nullable();
                $table->string('target_type')->default('all'); // all, specific
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('message_template');
                $table->string('status')->default('draft'); // draft, sent, partially_sent, failed
                $table->timestamp('sent_at')->nullable();
                $table->unsignedInteger('total_recipients')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('occasion_sms_logs')) {
            Schema::create('occasion_sms_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('occasion_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->string('phone');
                $table->text('message');
                $table->string('status')->default('sent'); // sent, failed
                $table->text('response_data')->nullable();
                $table->timestamps();

                $table->foreign('occasion_id')->references('id')->on('occasions')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occasion_sms_logs');
        Schema::dropIfExists('occasions');
    }
};
