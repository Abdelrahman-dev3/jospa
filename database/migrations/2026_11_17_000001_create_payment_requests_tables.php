<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mini invoices / payment links ("الفواتير وروابط الدفع").
 *
 * Kept in their own tables on purpose: they never touch the store's invoices,
 * payment_attempts or the booking finalizer, so existing checkout is unaffected.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'view_payment_request',
        'add_payment_request',
        'edit_payment_request',
        'delete_payment_request',
    ];

    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            // Human-readable number shown to the customer (e.g. PR-000123). Set right after insert.
            $table->string('number', 32)->nullable()->unique();
            // Secret part of the public URL (48 random chars). Never the numeric id.
            $table->string('token', 64)->unique();
            $table->string('customer_name', 150);
            $table->string('customer_mobile', 20)->nullable();
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');
            // pending | paid | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('paid_attempt_id')->nullable();
            // Unique: one gateway payment can settle one request only once.
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_request_attempts', function (Blueprint $table) {
            $table->id();
            // Public identifier used in the gateway return URL.
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('payment_request_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('brand', 20)->default('VISA');
            // pending | paid | failed | cancelled | expired
            $table->string('status', 20)->default('pending')->index();
            $table->string('merchant_transaction_id', 32)->unique();
            $table->string('checkout_id')->nullable()->unique();
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->string('result_code', 32)->nullable();
            $table->string('result_description')->nullable();
            // Whitelisted gateway fields only — never card data.
            $table->text('gateway_response')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        $this->createPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_request_attempts');
        Schema::dropIfExists('payment_requests');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', self::PERMISSIONS)->delete();
            app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
        }
    }

    /**
     * Same names the role screen derives from config('constant.MODULES') → "Payment Request".
     * Admins already pass every check through Gate::before; this also attaches them explicitly.
     */
    private function createPermissions(): void
    {
        $permissionModel = config('permission.models.permission');
        $roleModel = config('permission.models.role');

        if (! Schema::hasTable('permissions') || ! $permissionModel || ! class_exists($permissionModel)) {
            return;
        }

        try {
            foreach (self::PERMISSIONS as $name) {
                $attributes = ['name' => $name, 'guard_name' => 'web'];
                if (Schema::hasColumn('permissions', 'is_fixed')) {
                    $permissionModel::firstOrCreate($attributes, ['is_fixed' => true]);
                } else {
                    $permissionModel::firstOrCreate($attributes);
                }
            }

            $admin = $roleModel ? $roleModel::where('name', 'admin')->where('guard_name', 'web')->first() : null;
            $admin?->givePermissionTo(self::PERMISSIONS);

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Permissions can still be created from the roles screen; never block the schema change.
            report($e);
        }
    }
};
