<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wallet\Services\WalletTopupService;

class ReconcileWalletTopups extends Command
{
    protected $signature = 'wallet:reconcile-topups';

    protected $description = 'Settle pending app wallet top-ups with HyperPay and re-check user-cancelled ones (late payments are credited).';

    public function handle(WalletTopupService $topups): int
    {
        $count = $topups->reconcileOpenAttempts();
        $this->info("Checked {$count} wallet top-up attempt(s).");

        return self::SUCCESS;
    }
}
