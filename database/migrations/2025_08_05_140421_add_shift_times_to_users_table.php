<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('shift_start_time')->nullable()->after('mobile');
            $table->time('shift_end_time')->nullable()->after('shift_start_time');
        });
    }
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['shift_start_time', 'shift_end_time']);
        });
    }
};
