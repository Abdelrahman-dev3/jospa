<?php

namespace Modules\Package\Tests\Unit;

use Illuminate\Http\Request;
use Modules\Package\Http\Controllers\Backend\PackagesController;
use ReflectionClass;
use Tests\TestCase;

class PackageControllerDateNormalizationTest extends TestCase
{
    public function test_blank_package_dates_are_normalized_to_null(): void
    {
        $controller = new PackagesController();
        $request = new Request([
            'start_date' => '',
            'end_date' => '',
        ]);

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('normalizePackageDates');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNull($result->input('start_date'));
        $this->assertNull($result->input('end_date'));
    }

    public function test_explicit_package_price_is_preserved(): void
    {
        $controller = new PackagesController();
        $request = new Request([
            'package_price' => '450',
        ]);
        $services = [
            ['totalPrice' => 100],
            ['totalPrice' => 120],
        ];

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('resolvePackagePrice');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request, $services);

        $this->assertSame(450.0, $result);
    }

    public function test_package_price_falls_back_to_services_total_when_missing(): void
    {
        $controller = new PackagesController();
        $request = new Request();
        $services = [
            ['totalPrice' => 100],
            ['totalPrice' => 120.5],
        ];

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('resolvePackagePrice');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request, $services);

        $this->assertSame(220.5, $result);
    }

    public function test_prepare_package_services_preserves_service_fields(): void
    {
        $controller = new PackagesController();
        $services = [
            [
                'service_id' => '7',
                'service_name' => 'Hair Cut',
                'service_price' => '80',
                'qty' => '2',
                'discounted_price' => '80',
            ],
        ];

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('preparePackageServices');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $services);

        $this->assertSame([
            [
                'service_id' => 7,
                'service_price' => 80.0,
                'qty' => 2,
                'service_name' => 'Hair Cut',
                'discounted_price' => 80.0,
            ],
        ], $result);
    }
}
