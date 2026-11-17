<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentAttemptCancelService;
use Illuminate\Console\Command;

class RecheckCancelledCardPayments extends Command
{
    protected $signature = 'payments:recheck-cancelled-card';

    protected $description = 'Re-check HyperPay cart attempts the user cancelled; finalize any that were paid after the cancel.';

    public function handle(PaymentAttemptCancelService $cancels): int
    {
        $paid = $cancels->recheckCancelledHyperpay();
        $this->info("Finalized {$paid} late-paid card attempt(s).");

        return self::SUCCESS;
    }
}
