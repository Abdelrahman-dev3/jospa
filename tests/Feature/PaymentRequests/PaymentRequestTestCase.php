<?php

namespace Tests\Feature\PaymentRequests;

use App\Http\Middleware\BranchListCheck;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttempt;
use App\Models\Role;
use App\Models\User;
use App\Services\HyperpayService;
use App\Services\PaymentRequest\PaymentRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared setup for the payment-request tests. HyperPay is replaced by FakeHyperpay (no network,
 * no real charge). Builds only the tables this feature needs (the full migration set is MySQL-only).
 */
abstract class PaymentRequestTestCase extends TestCase
{
    protected FakeHyperpay $gateway;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        $this->gateway = new FakeHyperpay;
        $this->app->instance(HyperpayService::class, $this->gateway);

        config([
            'mail.default' => 'array',
            'services.hyperpay.base_url' => 'https://eu-prod.oppwa.com',
            'services.odoo.payment_invoice_sync_enabled' => false,
        ]);
        app()->setLocale('ar');

        // The real dashboard layout needs menus/branches/settings tables; render the tab inside a stub layout.
        $this->app['view']->getFinder()->prependLocation(base_path('tests/Feature/PaymentRequests/views'));
        $this->withoutMiddleware(BranchListCheck::class);

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = $this->makeUser('admin@example.test');
        $this->admin->assignRole('admin');
    }

    protected function startCheckout(PaymentRequest $pr, string $brand = 'VISA'): PaymentRequestAttempt
    {
        return app(PaymentRequestService::class)->startCheckout($pr->fresh(), $brand);
    }

    protected function makeRequest(array $data = [], array $state = []): PaymentRequest
    {
        $pr = app(PaymentRequestService::class)->create(array_merge([
            'customer_name' => 'Test Customer',
            'customer_mobile' => null,
            'description' => 'Home massage session',
            'amount' => '5000.00',
        ], $data), $this->admin->id ?? null);

        if ($state) {
            $pr->forceFill($state)->save();
        }

        return $pr->fresh();
    }

    protected function makeUser(string $email): User
    {
        return User::forceCreate([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('secret'),
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('username')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->string('mobile')->nullable();
            $t->string('password')->nullable();
            $t->string('user_type')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_notification_seen')->nullable();
            $t->rememberToken();
            $t->softDeletes();
            $t->timestamps();
        });

        // Read by the global CheckInstallation middleware.
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->text('val')->nullable();
            $t->string('type', 90)->default('string');
            $t->unsignedInteger('created_by')->nullable();
            $t->unsignedInteger('updated_by')->nullable();
            $t->unsignedInteger('deleted_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        (require base_path('database/migrations/2026_11_17_000001_create_payment_requests_tables.php'))->up();
        (require base_path('database/migrations/2026_11_18_000001_add_odoo_sync_to_payment_requests.php'))->up();
    }
}
