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
}
