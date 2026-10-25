<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'view_booking_reviews', 'guard_name' => 'web']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'view_booking_reviews')->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
