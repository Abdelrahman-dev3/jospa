<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Booking\Models\BookingPaymentLink;
use Modules\Booking\Services\BookingPaymentLinkService;

class ReconcileBookingPaymentLinks extends Command
{
    protected $signature = 'booking-links:reconcile {--hours=48 : Only links created within this many hours}';

    protected $description = 'Verify pending booking payment links with the gateway (covers customers who paid but never returned to the site) and expire stale links';

    public function handle(BookingPaymentLinkService $service): int
    {
        BookingPaymentLink::where('status', BookingPaymentLink::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => BookingPaymentLink::STATUS_EXPIRED]);

        // Links that expired while a checkout was still open are also checked below via their attempt.
        $links = BookingPaymentLink::whereNotNull('payment_attempt_id')
            ->whereIn('status', [BookingPaymentLink::STATUS_PENDING, BookingPaymentLink::STATUS_EXPIRED])
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            ->whereHas('attempt', fn ($q) => $q->where('status', 'pending')->where('created_at', '>=', now()->subHours(6)))
            ->with(['booking', 'attempt'])
            ->get();

        $paid = 0;
        foreach ($links as $link) {
            try {
                if ($service->reconcile($link) === BookingPaymentLink::STATUS_PAID) {
                    $paid++;
                }
            } catch (\Throwable $e) {
                $this->error("Link #{$link->id}: {$e->getMessage()}");
            }
        }

        $this->info("Checked {$links->count()} link(s), {$paid} confirmed paid.");

        return Command::SUCCESS;
    }
}
