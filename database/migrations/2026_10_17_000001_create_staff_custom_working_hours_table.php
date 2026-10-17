<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_custom_working_hours')) {
            Schema::create('staff_custom_working_hours', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id');
                $table->date('date');
                $table->string('start_time')->nullable();
                $table->string('end_time')->nullable();
                $table->boolean('is_holiday')->default(false);
                $table->longText('breaks')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['staff_id', 'date']);
                $table->index('staff_id');

                if (Schema::hasTable('users')) {
                    $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_custom_working_hours');
    }
};
