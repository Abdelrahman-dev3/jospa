<?php

namespace App\Console\Commands;

use App\Services\PaymentRequest\PaymentRequestService;
use Illuminate\Console\Command;

class ReconcilePaymentRequests extends Command
{
    protected $signature = 'payment-requests:reconcile';

    protected $description = 'Ask HyperPay about open payment-link checkouts and settle them (covers customers who paid but never returned).';

    public function handle(PaymentRequestService $service): int
    {
        $count = $service->reconcileOpenAttempts();
        $this->info("Checked {$count} payment request attempt(s).");

        return self::SUCCESS;
    }
}
