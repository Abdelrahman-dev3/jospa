<?php

namespace Tests\Unit;

use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentSubMethodsSelectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_disabled_options_never_access_or_deduct_balances(): void
    {
        $container = new Container();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        // Any model access would fail: no database connection is available.
        $database = Mockery::mock();
        $database->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());
        DB::swap($database);

        foreach ([null, false, 0, '0', 'false', 'off', 'no', ''] as $value) {
            foreach ([false, true] as $commit) {
                $input = $value === null ? [] : ['wallet' => $value, 'loyalty' => $value];
                $result = (new PaymentSubMethodsService())->apply(
                    1, Request::create('/payment', 'POST', $input), 907.20, $commit
                );
                self::assertEqualsWithDelta(907.20, $result['remaining_amount'], 0.00001);
                self::assertSame(0, $result['used_wallet']);
                self::assertSame(0, $result['used_loyalty']);
            }
        }
    }
}
