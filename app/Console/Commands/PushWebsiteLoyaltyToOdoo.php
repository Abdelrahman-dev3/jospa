<?php

namespace App\Console\Commands;

use App\Models\LoyaltyPoint;
use App\Models\User;
use App\Services\OdooLoyaltyService;
use Illuminate\Console\Command;

class PushWebsiteLoyaltyToOdoo extends Command
{
    protected $signature = 'odoo:push-website-loyalty
                            {--dry-run : Preview what would change without actually sending to Odoo}
                            {--user= : Push only a specific user ID}';

    protected $description = 'Push website loyalty balances to Odoo — website is the source of truth. If Odoo differs, it gets overwritten.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificUserId = $this->option('user');

        $query = LoyaltyPoint::query();

        if ($specificUserId) {
            $query->where('user_id', $specificUserId);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->info('No loyalty point records found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d loyalty records to process.', $records->count()));

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be sent to Odoo.');
        }

        $odooService = app(OdooLoyaltyService::class);
        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $alreadyEqual = 0;

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $loyalty) {
            $user = User::find($loyalty->user_id);

            if (! $user || empty($user->mobile)) {
                $this->newLine();
                $this->warn("  Skipped user #{$loyalty->user_id} — no mobile number.");
                $skipped++;
                $bar->advance();
                continue;
            }

            $localPoints = (float) $loyalty->points;
            $customerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $reference = 'WEBSITE-PUSH-' . $loyalty->user_id . '-' . now()->format('Ymd');

            if ($dryRun) {
                // In dry-run, fetch Odoo balance to show what would happen
                $balanceResult = $odooService->getBalance($user->mobile);
                $odooPoints = ($balanceResult['success'] ?? false) ? (float) ($balanceResult['points'] ?? 0) : '??';

                $this->newLine();
                $this->line(sprintf(
                    '  [DRY] User #%d (%s) — phone: %s — website: %s — odoo: %s — %s',
                    $user->id,
                    $customerName,
                    $user->mobile,
                    $localPoints,
                    $odooPoints,
                    ($odooPoints !== '??' && round($localPoints, 2) === round((float) $odooPoints, 2))
                        ? 'ALREADY EQUAL'
                        : 'WILL PUSH'
                ));
                $bar->advance();
                continue;
            }

            try {
                // 1. Fetch current Odoo balance
                $balanceResult = $odooService->getBalance($user->mobile);

                if (! ($balanceResult['success'] ?? false)) {
                    $failed++;
                    $this->newLine();
                    $this->error("  Failed: User #{$user->id} — Could not fetch balance from Odoo.");
                    $bar->advance();
                    continue;
                }

                $odooPoints = (float) ($balanceResult['points'] ?? 0);

                // 2. If already equal, skip
                if (round($localPoints, 2) === round($odooPoints, 2)) {
                    $alreadyEqual++;
                    $bar->advance();
                    continue;
                }

                // 3. Calculate the difference: website is the source of truth
                //    If website=500, odoo=750 → deduct 250 from Odoo
                //    If website=500, odoo=300 → add 200 to Odoo
                $difference = $localPoints - $odooPoints;
                $operation = $difference > 0 ? 'add' : 'deduct';
                $pointsToAdjust = abs($difference);

                $result = $odooService->adjust(
                    phone: $user->mobile,
                    operation: $operation,
                    points: $pointsToAdjust,
                    reference: $reference,
                    note: "Website push: setting Odoo to match website balance ({$localPoints}). Was {$odooPoints} in Odoo.",
                    name: $customerName !== '' ? $customerName : null,
                );

                if ($result['success'] ?? false) {
                    $synced++;
                    $this->newLine();
                    $this->info(sprintf(
                        '  ✓ User #%d (%s) — Odoo: %s → %s (%s %s)',
                        $user->id,
                        $customerName,
                        $odooPoints,
                        $localPoints,
                        $operation,
                        $pointsToAdjust
                    ));
                } else {
                    $failed++;
                    $this->newLine();
                    $this->error("  Failed: User #{$user->id} — " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  Exception: User #{$user->id} — " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Push complete: {$synced} pushed, {$alreadyEqual} already equal, {$failed} failed, {$skipped} skipped.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
