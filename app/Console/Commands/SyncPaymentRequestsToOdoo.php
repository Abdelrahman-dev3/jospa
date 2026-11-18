<?php

namespace App\Console\Commands;

use App\Services\PaymentRequest\OdooPaymentInvoiceSyncService;
use Illuminate\Console\Command;

class SyncPaymentRequestsToOdoo extends Command
{
    protected $signature = 'payment-requests:sync-odoo';

    protected $description = 'Send paid payment-link invoices to Odoo (event odoo_create_payment_invoice) and retry failed sends.';

    public function handle(OdooPaymentInvoiceSyncService $sync): int
    {
        if (! OdooPaymentInvoiceSyncService::enabled()) {
            $this->info('Odoo payment invoice sync is disabled (ODOO_PAYMENT_INVOICE_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        $count = $sync->syncDue();
        $this->info("Attempted {$count} payment request(s).");

        return self::SUCCESS;
    }
}
