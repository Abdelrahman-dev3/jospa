<?php

namespace Tests\Feature\PaymentRequests;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttempt;
use App\Notifications\PaymentRequestPaid;
use App\Services\PaymentRequest\PaymentRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Mini invoices & payment links. HyperPay is replaced by FakeHyperpay: no network, no real charge.
 * Builds only the tables this feature needs (the project's full migration set is MySQL-only).
 */
class PaymentRequestTest extends PaymentRequestTestCase
{
    // ------------------------------------------------------------------ creation

    public function test_admin_creates_a_5000_sar_invoice_with_server_generated_number_and_token(): void
    {
        $this->actingAs($this->admin)
            ->post(route('backend.payment-requests.store'), [
                'customer_name' => 'محمد أحمد',
                'customer_mobile' => '0551234567',
                'description' => 'جلسة مساج منزلية',
                'amount' => '5,000',
                // Not form fields: must be ignored.
                'status' => 'paid',
                'number' => 'HACK-1',
            ])
            ->assertRedirect(route('backend.payment-requests.index'))
            ->assertSessionHas('new_payment_request_id');

        $pr = PaymentRequest::sole();
        $this->assertSame('5000.00', $pr->amount);
        $this->assertSame('PR-000001', $pr->number);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->status);
        $this->assertSame('+966551234567', $pr->customer_mobile);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $pr->token);
        $this->assertSame($this->admin->id, $pr->created_by);
        $this->assertStringContainsString('/pay/invoice/'.$pr->token, $pr->publicUrl());
        $this->assertStringStartsWith('https://wa.me/966551234567?text=', $pr->whatsappUrl());
        $this->assertStringContainsString('شامل الضريبة', rawurldecode($pr->whatsappUrl()));
    }

    public function test_arabic_digits_are_accepted_and_mobile_is_optional(): void
    {
        $this->actingAs($this->admin)->post(route('backend.payment-requests.store'), [
            'customer_name' => 'Sara', 'description' => 'Service', 'amount' => '١٢٥٠٫٥',
        ])->assertSessionHasNoErrors();

        $pr = PaymentRequest::sole();
        $this->assertSame('1250.50', $pr->amount);
        $this->assertNull($pr->customer_mobile);
        $this->assertStringStartsWith('https://wa.me/?text=', $pr->whatsappUrl());
    }

    public static function invalidAmounts(): array
    {
        return [
            'zero' => ['0'], 'negative' => ['-5'], 'text' => ['abc'], 'three decimals' => ['10.123'],
            'too large' => ['10000000'], 'below minimum' => ['0.50'], 'empty' => [''], 'exponent' => ['1e3'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected(string $amount): void
    {
        $this->actingAs($this->admin)
            ->post(route('backend.payment-requests.store'), ['customer_name' => 'A', 'description' => 'B', 'amount' => $amount])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, PaymentRequest::count());
    }

    public function test_required_fields_and_invalid_mobile_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('backend.payment-requests.store'), ['customer_mobile' => '123', 'amount' => '100'])
            ->assertSessionHasErrors(['customer_name', 'description']);

        $this->actingAs($this->admin)
            ->post(route('backend.payment-requests.store'), ['customer_name' => 'A', 'description' => 'B', 'customer_mobile' => '123', 'amount' => '100'])
            ->assertSessionHasErrors(['customer_mobile']);

        $this->assertSame(0, PaymentRequest::count());
    }

    public function test_invoice_numbers_are_unique_and_sequential(): void
    {
        $a = $this->makeRequest();
        $b = $this->makeRequest();

        $this->assertSame('PR-000001', $a->number);
        $this->assertSame('PR-000002', $b->number);
        $this->assertNotSame($a->token, $b->token);
    }

    // ------------------------------------------------------------------ authorization

    public function test_guest_cannot_reach_dashboard_routes(): void
    {
        $pr = $this->makeRequest();

        $this->get(route('backend.payment-requests.index'))->assertRedirect();
        $this->post(route('backend.payment-requests.store'), ['customer_name' => 'A', 'description' => 'B', 'amount' => '10'])->assertRedirect();
        $this->post(route('backend.payment-requests.cancel', $pr->id))->assertRedirect();

        $this->assertSame(1, PaymentRequest::count());
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->makeUser('staff@example.test');
        $pr = $this->makeRequest();

        $this->actingAs($user)->get(route('backend.payment-requests.index'))->assertForbidden();
        $this->actingAs($user)->post(route('backend.payment-requests.store'), ['customer_name' => 'A', 'description' => 'B', 'amount' => '10'])->assertForbidden();
        $this->actingAs($user)->post(route('backend.payment-requests.cancel', $pr->id))->assertForbidden();
        $this->actingAs($user)->post(route('backend.payment-requests.refresh', $pr->id))->assertForbidden();

        $this->assertSame(1, PaymentRequest::count());
    }

    public function test_view_only_user_can_list_but_not_create_or_cancel(): void
    {
        $user = $this->makeUser('viewer@example.test');
        $user->givePermissionTo('view_payment_request');
        $pr = $this->makeRequest();

        $this->actingAs($user)->get(route('backend.payment-requests.index'))
            ->assertOk()
            ->assertSee($pr->number)
            ->assertDontSee('action="'.route('backend.payment-requests.store').'"', false)
            ->assertDontSee('action="'.route('backend.payment-requests.cancel', $pr->id).'"', false);
        $this->actingAs($user)->post(route('backend.payment-requests.store'), ['customer_name' => 'A', 'description' => 'B', 'amount' => '10'])->assertForbidden();
        $this->actingAs($user)->post(route('backend.payment-requests.cancel', $pr->id))->assertForbidden();
    }

    public function test_dashboard_tab_lists_invoices_with_copy_and_whatsapp_actions(): void
    {
        $pending = $this->makeRequest(['customer_name' => 'عميل معلق']);
        $paid = $this->makeRequest(['customer_name' => 'عميل مدفوع'], ['status' => PaymentRequest::STATUS_PAID, 'paid_at' => now(), 'gateway_transaction_id' => '8ac7a4a1-ref']);

        $this->actingAs($this->admin)->get(route('backend.payment-requests.index'))
            ->assertOk()
            ->assertSee('الفواتير وروابط الدفع')
            ->assertSee($pending->number)->assertSee($paid->number)
            ->assertSee('5,000.00')
            ->assertSee($pending->publicUrl(), false)
            ->assertSee('https://wa.me/', false)
            ->assertSee('8ac7a4a1-ref')
            ->assertSee('شامل ضريبة القيمة المضافة')
            ->assertSee('المبلغ النهائي شامل الضريبة')
            ->assertSee('pr-create-form', false);
    }

    // ------------------------------------------------------------------ public page

    public function test_public_page_shows_invoice_without_login(): void
    {
        $pr = $this->makeRequest(['description' => 'جلسة مساج']);

        $this->get($pr->publicUrl())
            ->assertOk()
            ->assertSee($pr->number)
            ->assertSee($pr->customer_name)
            ->assertSee('جلسة مساج')
            ->assertSee('شامل ضريبة القيمة المضافة')
            ->assertSee('5,000.00')
            ->assertSee(route('payment-request.pay', $pr->token), false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_unknown_or_malformed_token_returns_404(): void
    {
        $this->makeRequest();

        $this->get('/pay/invoice/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/pay/invoice/short')->assertNotFound();
        $this->get('/pay/invoice/1')->assertNotFound();
        $this->post('/pay/invoice/'.str_repeat('b', 48), ['brand' => 'VISA'])->assertNotFound();
        $this->assertSame([], $this->gateway->checkouts);
    }

    // ------------------------------------------------------------------ checkout

    public function test_checkout_uses_the_stored_amount_not_browser_input(): void
    {
        $pr = $this->makeRequest();

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA', 'amount' => '1.00'])
            ->assertOk()
            ->assertSee('paymentWidgets', false)
            ->assertSee('/pay/invoice/return/', false);

        $this->assertCount(1, $this->gateway->checkouts);
        $this->assertSame(5000.0, $this->gateway->checkouts[0]['amount']);
        $this->assertSame('VISA', $this->gateway->checkouts[0]['brand']);

        $attempt = PaymentRequestAttempt::sole();
        $this->assertSame('5000.00', $attempt->amount);
        $this->assertSame(PaymentRequestAttempt::STATUS_PENDING, $attempt->status);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
    }

    public function test_invalid_brand_is_rejected(): void
    {
        $pr = $this->makeRequest();

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'BITCOIN'])->assertSessionHasErrors('brand');
        $this->assertSame([], $this->gateway->checkouts);
    }

    public function test_double_submit_reuses_the_open_checkout(): void
    {
        $pr = $this->makeRequest();

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])->assertOk();
        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])->assertOk();

        $this->assertCount(1, $this->gateway->checkouts);
        $this->assertSame(1, PaymentRequestAttempt::count());
    }

    public function test_switching_method_supersedes_the_previous_checkout(): void
    {
        $pr = $this->makeRequest();

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])->assertOk();
        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'MADA'])->assertOk();

        $this->assertCount(2, $this->gateway->checkouts);
        [$first, $second] = PaymentRequestAttempt::orderBy('id')->get()->all();
        $this->assertSame(PaymentRequestAttempt::STATUS_CANCELLED, $first->status);
        $this->assertSame(PaymentRequestService::SUPERSEDED_NOTE, $first->result_description);
        $this->assertSame(PaymentRequestAttempt::STATUS_PENDING, $second->status);
    }

    public function test_gateway_error_is_recorded_without_marking_anything(): void
    {
        $pr = $this->makeRequest();
        $this->gateway->failCheckout = true;

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])
            ->assertRedirect(route('payment-request.show', $pr->token))
            ->assertSessionHas('error');

        $this->assertSame(PaymentRequestAttempt::STATUS_FAILED, PaymentRequestAttempt::sole()->status);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
    }

    // ------------------------------------------------------------------ verification

    public function test_successful_payment_is_verified_server_side_and_marks_paid_with_notification(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        // Browser query params are ignored; only our own checkout id is looked up.
        $this->get(route('payment-request.return', $attempt->uuid).'?resourcePath=/v1/checkouts/SOMEONE_ELSE/payment&id=SOMEONE_ELSE')
            ->assertRedirect(route('payment-request.show', $pr->token));

        $this->assertSame(['/v1/checkouts/'.$attempt->checkout_id.'/payment'], array_values(array_unique($this->gateway->lookups)));

        $pr->refresh();
        $this->assertSame(PaymentRequest::STATUS_PAID, $pr->status);
        $this->assertSame('PAY-'.$attempt->checkout_id, $pr->gateway_transaction_id);
        $this->assertSame($attempt->id, (int) $pr->paid_attempt_id);
        $this->assertNotNull($pr->paid_at);
        $this->assertSame(PaymentRequestAttempt::STATUS_PAID, $attempt->fresh()->status);

        $notifications = $this->admin->fresh()->notifications;
        $this->assertCount(1, $notifications);
        $this->assertSame('payment_request', $notifications[0]->data['data']['notification_group']);
        $this->assertSame($pr->number, $notifications[0]->data['data']['number']);

        $this->get($pr->publicUrl())->assertOk()
            ->assertSee('تم تأكيد الدفع بنجاح')
            ->assertDontSee(route('payment-request.pay', $pr->token), false);
    }

    public function test_reaching_the_return_url_without_a_gateway_payment_does_not_mark_paid(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);

        $this->get(route('payment-request.return', $attempt->uuid).'?resourcePath=x&result=000.000.000')
            ->assertRedirect(route('payment-request.show', $pr->token));

        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
        $this->assertSame(0, $this->admin->fresh()->notifications()->count());
    }

    public function test_duplicate_callbacks_and_reconcile_settle_only_once(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        $this->get(route('payment-request.return', $attempt->uuid));
        $this->post(route('payment-request.return', $attempt->uuid));
        $this->get(route('payment-request.return', $attempt->uuid));
        $this->artisan('payment-requests:reconcile')->assertSuccessful();
        app(PaymentRequestService::class)->refresh($attempt->fresh(), force: true);

        $this->assertSame(PaymentRequest::STATUS_PAID, $pr->fresh()->status);
        $this->assertSame(1, PaymentRequestAttempt::where('status', 'paid')->count());
        $this->assertSame(1, $this->admin->fresh()->notifications()->count());
    }

    public function test_return_url_for_unknown_attempt_is_404(): void
    {
        $this->get('/pay/invoice/return/9b2f7f4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f')->assertNotFound();
        $this->get('/pay/invoice/return/not-a-uuid')->assertNotFound();
    }

    public function test_failed_payment_is_recorded_and_invoice_stays_payable(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->result($attempt, '800.100.151', 'transaction declined (invalid card)');

        $this->get(route('payment-request.return', $attempt->uuid))->assertSessionHas('error');

        $this->assertSame(PaymentRequestAttempt::STATUS_FAILED, $attempt->fresh()->status);
        $this->assertSame('800.100.151', $attempt->fresh()->result_code);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])->assertOk();
        $this->assertCount(2, $this->gateway->checkouts);
    }

    public function test_pending_bank_result_keeps_invoice_pending_and_reuses_the_same_checkout(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->result($attempt, '800.400.500', 'Waiting for confirmation of non-instant payment.');

        $this->get(route('payment-request.return', $attempt->uuid))->assertSessionHas('info');

        $this->assertSame(PaymentRequestAttempt::STATUS_PENDING, $attempt->fresh()->status);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
        $this->get($pr->publicUrl())->assertSee('لا تكرر الدفع');

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])->assertOk();
        $this->assertCount(1, $this->gateway->checkouts);
    }

    public function test_amount_mismatch_from_gateway_is_not_accepted(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt, amount: '1.00');

        $this->get(route('payment-request.return', $attempt->uuid));

        $this->assertSame(PaymentRequestAttempt::STATUS_FAILED, $attempt->fresh()->status);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
        $this->assertSame(0, $this->admin->fresh()->notifications()->count());
    }

    public function test_test_mode_success_on_live_gateway_is_not_accepted(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->result($attempt, '000.100.110', 'Request successfully processed in Merchant in Integrator Test Mode', withPayment: true);

        $this->get(route('payment-request.return', $attempt->uuid));

        $this->assertSame(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
    }

    public function test_card_data_is_not_stored(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        $this->get(route('payment-request.return', $attempt->uuid));

        $raw = (string) DB::table('payment_request_attempts')->value('gateway_response');
        $this->assertStringNotContainsString('411111', $raw);
        $this->assertStringNotContainsString('holder', $raw);
        $this->assertStringNotContainsString('card', $raw);
        $this->assertStringContainsString('PAY-'.$attempt->checkout_id, $raw);
    }

    // ------------------------------------------------------------------ closed invoices

    public function test_paid_invoice_cannot_start_a_new_charge(): void
    {
        $pr = $this->makeRequest([], ['status' => PaymentRequest::STATUS_PAID, 'paid_at' => now()]);

        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])
            ->assertRedirect(route('payment-request.show', $pr->token))
            ->assertSessionHas('error');

        $this->assertSame([], $this->gateway->checkouts);
    }

    public function test_cancelled_invoice_cannot_start_a_new_charge(): void
    {
        $pr = $this->makeRequest();
        $this->actingAs($this->admin)->post(route('backend.payment-requests.cancel', $pr->id))->assertSessionHas('success');
        $this->assertSame(PaymentRequest::STATUS_CANCELLED, $pr->fresh()->status);

        auth()->logout();
        $this->post(route('payment-request.pay', $pr->token), ['brand' => 'VISA'])->assertSessionHas('error');
        $this->get($pr->publicUrl())->assertSee('تم إلغاء هذه الفاتورة');
        $this->assertSame([], $this->gateway->checkouts);
    }

    public function test_cancel_checks_the_gateway_first_and_refuses_if_paid(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        $this->actingAs($this->admin)->post(route('backend.payment-requests.cancel', $pr->id))->assertSessionHas('error');

        $this->assertSame(PaymentRequest::STATUS_PAID, $pr->fresh()->status);
    }

    public function test_payment_completed_after_cancel_is_still_recorded(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->actingAs($this->admin)->post(route('backend.payment-requests.cancel', $pr->id));
        $this->assertSame(PaymentRequest::STATUS_CANCELLED, $pr->fresh()->status);

        // Customer finishes on the payment page that was already open; the 5-minute cron picks it up.
        $this->gateway->paid($attempt);
        $this->travel(5)->minutes();
        $this->artisan('payment-requests:reconcile')->assertSuccessful();

        $this->assertSame(PaymentRequest::STATUS_PAID, $pr->fresh()->status);
        $this->assertSame('payment_request_paid_after_cancel', $this->admin->fresh()->notifications[0]->data['data']['notification_type']);
    }

    public function test_second_payment_on_a_superseded_checkout_is_flagged_for_refund(): void
    {
        $pr = $this->makeRequest();
        $visa = $this->startCheckout($pr, 'VISA');
        $mada = $this->startCheckout($pr, 'MADA');

        $this->gateway->paid($mada);
        $this->get(route('payment-request.return', $mada->uuid));
        $this->gateway->paid($visa);
        $this->travel(5)->minutes();
        $this->artisan('payment-requests:reconcile')->assertSuccessful();

        $pr->refresh();
        $this->assertSame($mada->id, (int) $pr->paid_attempt_id);
        $this->assertSame(PaymentRequestAttempt::STATUS_PAID, $visa->fresh()->status);
        $this->assertSame(PaymentRequestService::DUPLICATE_PAYMENT_NOTE, $visa->fresh()->result_description);
        $types = $this->admin->fresh()->notifications->pluck('data.data.notification_type')->sort()->values()->all();
        $this->assertSame(['payment_request_duplicate', 'payment_request_paid'], $types);
    }

    public function test_refresh_action_settles_a_customer_who_never_returned(): void
    {
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        $this->actingAs($this->admin)->post(route('backend.payment-requests.refresh', $pr->id))->assertSessionHas('success');

        $this->assertSame(PaymentRequest::STATUS_PAID, $pr->fresh()->status);
    }

    // ------------------------------------------------------------------ email

    public function test_email_is_not_sent_when_no_mailer_is_configured(): void
    {
        Notification::fake();
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        $this->get(route('payment-request.return', $attempt->uuid));

        Notification::assertSentTo($this->admin, PaymentRequestPaid::class, fn ($n, $channels) => $channels === ['database']);
        Notification::assertSentToTimes($this->admin, PaymentRequestPaid::class, 1);
    }

    public function test_email_is_sent_when_the_site_mailer_is_configured(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.test']);
        $pr = $this->makeRequest();
        $attempt = $this->startCheckout($pr);
        $this->gateway->paid($attempt);

        $this->get(route('payment-request.return', $attempt->uuid));

        Notification::assertSentTo($this->admin, PaymentRequestPaid::class, fn ($n, $channels) => $channels === ['mail']);
        Notification::assertSentTo($this->admin, PaymentRequestPaid::class, fn ($n, $channels) => $channels === ['database']);
    }

    // ------------------------------------------------------------------ regression

    public function test_existing_store_checkout_routes_are_unchanged(): void
    {
        $this->assertSame(
            'App\Services\Payment\Strategies\CardPaymentStrategy@callbackPlain',
            Route::getRoutes()->getByName('hyperpay.callback.plain')->getActionName()
        );
        $this->assertSame(
            'App\Services\Payment\Strategies\CardPaymentStrategy@checkout',
            Route::getRoutes()->getByName('hyperpay.checkout')->getActionName()
        );
        $this->assertSame(
            'Modules\Booking\Http\Controllers\BookingPaymentLinkPublicController@show',
            Route::getRoutes()->getByName('booking-payment-link.show')->getActionName()
        );
        // The store's payment tables are never created in this test; any use of them by this feature would error.
        $this->assertFalse(Schema::hasTable('payment_attempts'));
    }
}
