<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Services\OdooBookingSyncService;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentSubMethodsService;
use App\Services\Payment\Strategies\BasePaymentStrategy;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class BookingCouponPaymentTest extends TestCase
{
    private $bookingQuery;
    private $couponQuery;
    private $serviceTaxRows;

    protected function setUp(): void
    {
        parent::setUp();
        Container::setInstance(new Container());
        app()->instance(\Illuminate\Contracts\Auth\Factory::class, new class {
            public function id() { return 1; }
        });
        app()->instance('translator', new class {
            public function get($key, $replace = [], $locale = null) { return $key; }
        });

        $this->bookingQuery = Mockery::mock('alias:Modules\\Booking\\Models\\Booking');
        $coupon = Mockery::mock('alias:Modules\\Promotion\\Models\\Coupon');
        $this->couponQuery = Mockery::mock();
        $coupon->shouldReceive('query')->andReturn($this->couponQuery);
        $this->couponQuery->shouldReceive('where')->with('coupon_code', 'TEST')->andReturnSelf();
        $this->couponQuery->shouldReceive('usable')->andReturnSelf();

        $cart = Mockery::mock('alias:Modules\\Product\\Models\\Cart');
        $cart->shouldReceive('with->where->get')->andReturn(collect());
        $gift = Mockery::mock('alias:App\\Models\\GiftCard');
        $gift->shouldReceive('where->where->get')->andReturn(collect());

        $tax = Mockery::mock('alias:Modules\\Tax\\Models\\Tax');
        $serviceTaxes = Mockery::mock();
        $tax->shouldReceive('active')->andReturn($serviceTaxes);
        $serviceTaxes->shouldReceive('whereNull', 'orWhere', 'where')->andReturnSelf();
        $this->serviceTaxRows = collect([
            new Fluent(['type' => 'percent', 'value' => 15, 'title' => 'VAT']),
        ]);
        $serviceTaxes->shouldReceive('get')->andReturnUsing(fn () => $this->serviceTaxRows);
        $tax->shouldReceive('where->where->get')->andReturn(collect());

        $settings = Mockery::mock('alias:App\\Support\\FrontendPaymentSettings');
        $settings->shouldReceive('paymentGatewayDiscountAmount')->andReturn(0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_coupon_totals_match_odoo_for_both_checkout_paths(): void
    {
        $cases = [
            [[100], [0], 'percent', 10, 103.50],
            [[100], [0], 'fixed', 10, 105.00],
            [[100], [20], 'percent', 10, 82.80],
            [[100], [20], 'fixed', 10, 82.00],
            [[100, 200], [0, 0], 'percent', 10, 310.50],
            [[100, 200], [20, 30], 'fixed', 25, 262.50],
            [[100], [0], 'fixed', 200, 0.00],
            [[99.99], [0], 'percent', 15, 97.74],
        ];

        foreach ($cases as [$prices, $discounts, $type, $value, $expected]) {
            $bookings = $this->bookings($prices, $discounts);
            $coupon = Mockery::mock();
            $coupon->discount_type = $type;
            $coupon->discount_percentage = $type === 'percent' ? $value : 0;
            $coupon->discount_amount = $type === 'fixed' ? $value : 0;
            $coupon->shouldReceive('syncExpiredState')->twice();
            $this->couponQuery->shouldReceive('first')->twice()->andReturn($coupon);
            $this->bookingQuery->shouldReceive('getUserIncompleteBookings')->twice()->andReturn($bookings);

            foreach (['payment', 'cart'] as $page) {
                $result = (new PaymentCalculatorService())->calculateTotal($page, ' TEST ', null, 1);
                self::assertEqualsWithDelta($expected, $result['total'], 0.00001);

                $invoice = new Invoice([
                    'final_total' => $result['total'],
                    'discount_amount' => $result['discountAmount'],
                    'coupon_code' => 'TEST',
                    'user_id' => 1,
                    'payment_method' => 'card',
                ]);
                $invoice->id = 1;
                $method = new ReflectionMethod(OdooBookingSyncService::class, 'buildPayload');
                $payload = $method->invoke(new OdooBookingSyncService(), $invoice, $bookings, collect());

                self::assertEqualsWithDelta($expected, $payload['total'], 0.00001);
                self::assertEqualsWithDelta($result['couponDiscountAmount'], $payload['coupon_discount'], 0.00001);
                self::assertCount(count($prices), $payload['booking_details']);
                self::assertEqualsWithDelta(
                    array_sum($prices) - array_sum($discounts),
                    collect($payload['booking_details'])->sum('pricing.price'),
                    0.00001
                );
            }
        }
    }

    public function test_both_coupon_field_names_reach_the_actual_calculator(): void
    {
        $bookings = $this->bookings([100], [0]);
        $this->bookingQuery->shouldReceive('getUserIncompleteBookings')->andReturn($bookings);
        $coupon = Mockery::mock();
        $coupon->discount_type = 'percent';
        $coupon->discount_percentage = 10;
        $coupon->shouldReceive('syncExpiredState')->times(3);
        $this->couponQuery->shouldReceive('first')->times(3)->andReturn($coupon);

        $submethods = Mockery::mock(PaymentSubMethodsService::class);
        $submethods->shouldReceive('apply')->with(1, Mockery::type(Request::class), 103.5)
            ->times(3)->andReturn(['remaining_amount' => 103.5]);
        app()->instance(PaymentSubMethodsService::class, $submethods);

        $strategy = new class extends BasePaymentStrategy {
            public function prepare(Request $request): array
            {
                return $this->preparePaymentFlow($request, 'payment', 'card');
            }
        };

        foreach ([['invoiceCopon' => ' TEST '], ['coupon_code' => ' TEST '],
            ['invoiceCopon' => ' ', 'coupon_code' => 'TEST']] as $input) {
            $result = $strategy->prepare(Request::create('/payment', 'POST', $input));
            self::assertSame('TEST', $result['data']['couponCode']);
            self::assertEqualsWithDelta(103.5, $result['remainingAmount'], 0.00001);
            self::assertEqualsWithDelta(11.5, $result['data']['couponDiscountAmount'], 0.00001);
        }
    }

    public function test_invalid_coupon_does_not_produce_a_payable_total(): void
    {
        $this->bookingQuery->shouldReceive('getUserIncompleteBookings')->andReturn($this->bookings([100], [0]));
        $this->couponQuery->shouldReceive('first')->once()->andReturn(null);
        $result = (new PaymentCalculatorService())->calculateTotal('payment', 'TEST', null, 1);
        self::assertSame('messages.invalid_coupon', $result['error']);
        self::assertArrayNotHasKey('total', $result);
    }

    public function test_mixed_gift_and_booking_share_tax_and_coupon_without_rounding_loss(): void
    {
        $this->serviceTaxRows = collect([new Fluent(['type' => 'fixed', 'value' => 10, 'title' => 'Fixed tax'])]);
        Mockery::mock('alias:Modules\\Service\\Models\\Service')->shouldReceive('whereIn->get')->andReturn(collect());
        Mockery::mock('alias:Modules\\Package\\Models\\Package')->shouldReceive('whereIn->get')->andReturn(collect());
        $bookings = $this->bookings([100], [0]);
        $gift = new Fluent([
            'id' => 2, 'user_id' => 1, 'subtotal' => 100, 'delivery_method' => 'email',
            'requested_services' => [], 'package_ids' => [], 'user' => $bookings->first()->user,
        ]);

        foreach ([21.00, 0.01] as $discount) {
            $invoice = new Invoice(['final_total' => 210 - $discount, 'discount_amount' => $discount]);
            $invoice->id = 1;
            $payload = (new ReflectionMethod(OdooBookingSyncService::class, 'buildPayload'))
                ->invoke(new OdooBookingSyncService(), $invoice, $bookings, collect([$gift]));
            self::assertEqualsWithDelta(10, $payload['tax'] + $payload['gift_tax'], 0.00001);
            self::assertEqualsWithDelta($discount, $payload['coupon_discount'] + $payload['gift_coupon_discount'], 0.00001);
            self::assertEqualsWithDelta(210 - $discount, $payload['total'] + $payload['gift_total'], 0.00001);
        }
    }

    private function bookings(array $prices, array $discounts)
    {
        $rows = collect($prices)->map(fn ($price, $index) => (object) [
            'id' => $index + 1,
            'service_price' => $price,
            'discount_amount' => $discounts[$index],
            'service' => null,
            'employee' => null,
        ]);

        return collect([(object) [
            'id' => 1,
            'user_id' => 1,
            'services' => $rows,
            'booking_service' => $rows,
            'service' => $rows->first(),
            'bookingTransaction' => null,
            'user' => (object) ['first_name' => 'Test', 'last_name' => 'Customer', 'email' => null, 'mobile' => null],
            'branch' => null,
            'note' => null,
            'start_date_time' => null,
        ]]);
    }
}
