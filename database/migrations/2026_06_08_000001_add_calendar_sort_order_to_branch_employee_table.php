<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_employee', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_employee', 'calendar_sort_order')) {
                $table->unsignedInteger('calendar_sort_order')->nullable()->after('is_primary')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('branch_employee', function (Blueprint $table) {
            if (Schema::hasColumn('branch_employee', 'calendar_sort_order')) {
                $table->dropColumn('calendar_sort_order');
            }
        });
    }
};
