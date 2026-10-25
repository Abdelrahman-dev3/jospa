<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reviews', function (Blueprint $table) {
            $table->id();
            // One review per booking: the unique key is what prevents duplicates and double points.
            $table->unsignedBigInteger('booking_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('token', 64)->unique();
            // requested -> in_progress -> completed
            $table->string('status', 20)->default('requested')->index();
            // website | whatsapp (set when the review is completed)
            $table->string('source', 20)->nullable()->index();
            $table->unsignedTinyInteger('current_step')->default(0);
            // 4 = excellent ... 1 = unsatisfied
            $table->unsignedTinyInteger('overall_rating')->nullable();
            $table->unsignedTinyInteger('specialist_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->string('last_inbound_message_id')->nullable();
            $table->timestamp('request_sent_at')->nullable();
            $table->string('request_message_id')->nullable();
            $table->unsignedSmallInteger('request_attempts')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('points_awarded_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reviews');
    }
};
