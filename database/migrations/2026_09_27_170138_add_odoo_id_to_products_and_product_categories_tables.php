<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('odoo_id')->nullable()->index();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('odoo_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('odoo_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('odoo_id');
        });
    }
};