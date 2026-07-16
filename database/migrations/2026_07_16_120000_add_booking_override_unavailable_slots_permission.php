<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate([
            'name' => 'booking_override_unavailable_slots',
            'guard_name' => 'web',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'booking_override_unavailable_slots')
            ->where('guard_name', 'web')
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
