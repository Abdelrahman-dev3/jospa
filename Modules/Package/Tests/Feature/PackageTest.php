<?php

namespace Modules\Package\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Package\Models\Package;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test Package.
     *
     * @return void
     */
    public function test_backend_packages_list_page()
    {
        $this->signInAsAdmin();

        $response = $this->get('app/packages');

        $response->assertStatus(200);
    }

    public function test_package_can_be_created_without_expiry_dates()
    {
        $this->signInAsAdmin();

        $response = $this->post(route('backend.package.store'), [
            'name' => 'Unlimited Package',
            'description' => 'No expiry package',
            'status' => 1,
            'services' => '[]',
            'employee_id' => '',
            'category_id' => '',
            'start_date' => '',
            'end_date' => '',
        ]);

        $response->assertStatus(200);

        $package = Package::latest()->first();

        $this->assertNotNull($package);
        $this->assertNull($package->start_date);
        $this->assertNull($package->end_date);
    }
}
