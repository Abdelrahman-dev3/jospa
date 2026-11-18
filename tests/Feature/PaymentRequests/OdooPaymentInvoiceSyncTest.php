<?php

namespace Tests\Feature\PaymentRequests;

use App\Models\PaymentRequest;
use App\Services\PaymentRequest\OdooPaymentInvoiceSyncService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Sending paid payment-link invoices to Odoo. Odoo is faked with Http::fake — nothing leaves the machine. */
class OdooPaymentInvoiceSyncTest extends PaymentRequestTestCase
{
    private const ODOO_URL = 'https://odoo.example.test/jospa/webhook';

    private const PDF = "%PDF-1.4\n% fake odoo invoice\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'services.odoo.booking_create_url' => self::ODOO_URL,
            'services.odoo.db' => 'jospa-db',
            'services.odoo.login' => 'site@jospa.test',
            'services.odoo.password' => 'secret-pass',
        ]);
    }

    public function test_nothing_is_sent_while_the_sync_is_disabled(): void
    {
        Http::fake();
        $pr = $this->payThroughReturnUrl();

        Http::assertNothingSent();
        $this->assertSame(PaymentRequest::ODOO_PENDING, $pr->fresh()->odoo_sync_status);

        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_paid_invoice_is_sent_with_the_agreed_payload_and_odoo_result_is_stored(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/2026/00015'))]);

        $pr = $this->payThroughReturnUrl(['customer_name' => 'محمد أحمد', 'customer_mobile' => '+966551234567', 'description' => 'جلسة مساج منزلية 3 مرات']);

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) use ($pr) {
            $body = json_decode($request->body(), true);

            return $request->url() === self::ODOO_URL
                && $request->method() === 'POST'
                && $request->header('db')[0] === 'jospa-db'
                && $request->header('login')[0] === 'site@jospa.test'
                && $request->header('password')[0] === 'secret-pass'
                && $body['event'] === 'odoo_create_payment_invoice'
                && $body['data'] === [
                    'id' => $pr->id,
                    'number' => $pr->number,
                    'customer_name' => 'محمد أحمد',
                    'customer_mobile' => '+966551234567',
                    'note' => 'جلسة مساج منزلية 3 مرات',
                    'total' => 5000,
                    'currency' => 'SAR',
                    'tax_included' => true,
                    'payment_type' => 'Visa',
                    'payment_status' => 'Paid',
                    'transaction_reference' => $pr->fresh()->gateway_transaction_id,
                    'date_of_paid' => $pr->fresh()->paid_at->format('Y-m-d H:i:s'),
                ];
        });

        $pr->refresh();
        $this->assertSame(PaymentRequest::ODOO_SYNCED, $pr->odoo_sync_status);
        $this->assertSame('INV/2026/00015', $pr->odoo_invoice_number);
        $this->assertSame(1, $pr->odoo_sync_attempts);
        $this->assertNotNull($pr->odoo_synced_at);
        Storage::disk('local')->assertExists($pr->odoo_invoice_pdf);
    }

    public function test_mada_payments_are_sent_as_mada(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/1'))]);

        $this->payThroughReturnUrl([], 'MADA');

        Http::assertSent(fn (HttpRequest $r) => json_decode($r->body(), true)['data']['payment_type'] === 'Mada');
    }

    public function test_already_recorded_answer_counts_as_success(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/2026/00015', alreadyRecorded: true))]);

        $pr = $this->payThroughReturnUrl();

        $this->assertSame(PaymentRequest::ODOO_SYNCED, $pr->fresh()->odoo_sync_status);
        $this->assertSame('INV/2026/00015', $pr->fresh()->odoo_invoice_number);
    }

    public function test_failure_is_recorded_and_retried_by_the_scheduler(): void
    {
        $this->enable();
        Http::fakeSequence(self::ODOO_URL)
            ->push(['jsonrpc' => '2.0', 'result' => ['status' => 'error', 'message' => 'Partner creation failed']])
            ->pushStatus(500)
            ->push($this->odooSuccess('INV/2026/00020'));

        $pr = $this->payThroughReturnUrl();
        $this->assertSame(PaymentRequest::ODOO_FAILED, $pr->fresh()->odoo_sync_status);
        $this->assertStringContainsString('Partner creation failed', $pr->fresh()->odoo_last_error);

        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();
        $this->assertSame(PaymentRequest::ODOO_FAILED, $pr->fresh()->odoo_sync_status);
        $this->assertStringContainsString('HTTP 500', $pr->fresh()->odoo_last_error);

        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();
        $pr->refresh();
        $this->assertSame(PaymentRequest::ODOO_SYNCED, $pr->odoo_sync_status);
        $this->assertSame('INV/2026/00020', $pr->odoo_invoice_number);
        $this->assertSame(3, $pr->odoo_sync_attempts);
        $this->assertNull($pr->odoo_last_error);
    }

    public function test_connection_error_is_recorded_as_failed(): void
    {
        $this->enable();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $pr = $this->payThroughReturnUrl();

        $this->assertSame(PaymentRequest::ODOO_FAILED, $pr->fresh()->odoo_sync_status);
        $this->assertStringContainsString('timed out', $pr->fresh()->odoo_last_error);
    }

    public function test_synced_invoice_is_never_sent_again(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/1'))]);

        $pr = $this->payThroughReturnUrl();
        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();
        app(OdooPaymentInvoiceSyncService::class)->sync($pr->fresh());

        Http::assertSentCount(1);
    }

    public function test_automatic_retries_stop_after_the_limit(): void
    {
        $this->enable();
        Http::fake();
        $pr = $this->makeRequest([], [
            'status' => PaymentRequest::STATUS_PAID, 'paid_at' => now(),
            'odoo_sync_status' => PaymentRequest::ODOO_FAILED,
            'odoo_sync_attempts' => OdooPaymentInvoiceSyncService::MAX_ATTEMPTS,
        ]);

        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(PaymentRequest::ODOO_FAILED, $pr->fresh()->odoo_sync_status);
    }

    public function test_admin_can_resend_manually_after_the_limit(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/9'))]);
        $pr = $this->makeRequest([], [
            'status' => PaymentRequest::STATUS_PAID, 'paid_at' => now(), 'gateway_transaction_id' => 'PAY-X',
            'odoo_sync_status' => PaymentRequest::ODOO_FAILED,
            'odoo_sync_attempts' => OdooPaymentInvoiceSyncService::MAX_ATTEMPTS,
        ]);

        $this->actingAs($this->admin)->post(route('backend.payment-requests.odoo-sync', $pr->id))->assertSessionHas('success');

        $this->assertSame(PaymentRequest::ODOO_SYNCED, $pr->fresh()->odoo_sync_status);
    }

    public function test_unpaid_or_cancelled_invoices_are_never_sent(): void
    {
        $this->enable();
        Http::fake();
        $pending = $this->makeRequest([], ['odoo_sync_status' => PaymentRequest::ODOO_PENDING]);
        $cancelled = $this->makeRequest([], ['status' => PaymentRequest::STATUS_CANCELLED, 'odoo_sync_status' => PaymentRequest::ODOO_PENDING]);

        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();
        $this->actingAs($this->admin)->post(route('backend.payment-requests.odoo-sync', $pending->id))->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(PaymentRequest::ODOO_PENDING, $cancelled->fresh()->odoo_sync_status);
    }

    public function test_duplicate_payment_does_not_create_a_second_odoo_invoice(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/1'))]);
        $pr = $this->makeRequest();
        $visa = $this->startCheckout($pr, 'VISA');
        $mada = $this->startCheckout($pr, 'MADA');

        $this->gateway->paid($mada);
        $this->get(route('payment-request.return', $mada->uuid));
        $this->gateway->paid($visa);
        $this->travel(5)->minutes();
        $this->artisan('payment-requests:reconcile')->assertSuccessful();
        $this->artisan('payment-requests:sync-odoo')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_invoice_pdf_is_available_to_admin_and_to_the_customer_link_only(): void
    {
        $this->enable();
        Http::fake([self::ODOO_URL => Http::response($this->odooSuccess('INV/2026/00015'))]);
        $pr = $this->payThroughReturnUrl();

        $this->actingAs($this->admin)->get(route('backend.payment-requests.invoice-pdf', $pr->id))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(self::PDF, $this->actingAs($this->admin)->get(route('backend.payment-requests.invoice-pdf', $pr->id))->getContent());

        $this->actingAs($this->admin)->get(route('backend.payment-requests.index'))
            ->assertSee('مسجلة في أودو')->assertSee('INV/2026/00015')
            ->assertSee(route('backend.payment-requests.invoice-pdf', $pr->id), false);

        $staff = $this->makeUser('staff@example.test');
        $this->actingAs($staff)->get(route('backend.payment-requests.invoice-pdf', $pr->id))->assertForbidden();

        auth()->logout();
        $this->get($pr->publicUrl())->assertSee(route('payment-request.invoice-pdf', $pr->token), false);
        $this->get(route('payment-request.invoice-pdf', $pr->token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/pay/invoice/'.str_repeat('z', 48).'/invoice.pdf')->assertNotFound();
    }

    public function test_pdf_link_is_404_for_unpaid_invoice(): void
    {
        $pr = $this->makeRequest([], ['odoo_invoice_pdf' => 'payment-requests/odoo/x.pdf']);

        $this->get(route('payment-request.invoice-pdf', $pr->token))->assertNotFound();
    }

    public function test_failed_status_and_resend_button_show_in_the_dashboard(): void
    {
        $this->enable();
        $pr = $this->makeRequest([], [
            'status' => PaymentRequest::STATUS_PAID, 'paid_at' => now(),
            'odoo_sync_status' => PaymentRequest::ODOO_FAILED, 'odoo_last_error' => 'HTTP 500: boom',
        ]);

        $this->actingAs($this->admin)->get(route('backend.payment-requests.index'))
            ->assertSee('فشل الإرسال لأودو')
            ->assertSee(route('backend.payment-requests.odoo-sync', $pr->id), false);
    }

    // ------------------------------------------------------------------ helpers

    private function enable(): void
    {
        config(['services.odoo.payment_invoice_sync_enabled' => true]);
    }

    /** Create a request, open a checkout, let the (fake) gateway confirm it and hit the return URL. */
    private function payThroughReturnUrl(array $data = [], string $brand = 'VISA'): PaymentRequest
    {
        $pr = $this->makeRequest($data);
        $attempt = $this->startCheckout($pr, $brand);
        $this->gateway->paid($attempt);
        $this->get(route('payment-request.return', $attempt->uuid))->assertRedirect();

        return $pr->fresh();
    }

    private function odooSuccess(string $number, bool $alreadyRecorded = false): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => null,
            'result' => [
                'status' => 'success',
                'invoice_number' => $number,
                'already_recorded' => $alreadyRecorded,
                'invoice_pdf' => base64_encode(self::PDF),
            ],
        ];
    }
}
