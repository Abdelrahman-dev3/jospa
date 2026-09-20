<?php

namespace App\Console\Commands;

use App\Models\LoyaltyPoint;
use App\Models\User;
use App\Services\OdooLoyaltyService;
use Illuminate\Console\Command;

class SyncLoyaltyToOdoo extends Command
{
    protected $signature = 'odoo:sync-loyalty
                            {--dry-run : Preview what would be synced without actually sending to Odoo}
                            {--user= : Sync only a specific user ID}';

    protected $description = 'One-time sync to push missing website loyalty balances to Odoo using add/deduct operations.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificUserId = $this->option('user');

        $query = LoyaltyPoint::where('points', '>', 0);

        if ($specificUserId) {
            $query->where('user_id', $specificUserId);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->info('No loyalty point records with balance > 0 found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d loyalty records to sync.', $records->count()));

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be sent to Odoo.');
        }

        $odooService = app(OdooLoyaltyService::class);
        $success = 0;
        $duplicates = 0;
        $failed = 0;
        $skipped = 0;

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

            $reference = 'INITIAL-SYNC-' . $loyalty->user_id;
            $customerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            if ($dryRun) {
                $this->newLine();
                $this->line(sprintf(
                    '  [DRY] User #%d (%s) — phone: %s — points: %s — ref: %s',
                    $user->id,
                    $customerName,
                    $user->mobile,
                    $loyalty->points,
                    $reference,
                ));
                $bar->advance();
                continue;
            }

            try {
                // First fetch the Odoo balance
                $balanceResult = $odooService->getBalance($user->mobile);
                
                if (!($balanceResult['success'] ?? false)) {
                    $failed++;
                    $this->newLine();
                    $this->error("  Failed: User #{$user->id} — Could not fetch balance from Odoo.");
                    $bar->advance();
                    continue;
                }

                $odooPoints = (float) ($balanceResult['points'] ?? 0);
                $localPoints = (float) $loyalty->points;
                
                if (round($localPoints, 2) === round($odooPoints, 2)) {
                    $skipped++;
                    $bar->advance();
                    continue; // Already synchronized
                }

                $difference = $localPoints - $odooPoints;
                $operation = $difference > 0 ? 'add' : 'deduct';
                $pointsToAdjust = abs($difference);

                $result = $odooService->adjust(
                    phone: $user->mobile,
                    operation: $operation,
                    points: $pointsToAdjust,
                    reference: $reference,
                    note: 'Initial sync: equalizing difference between system and Odoo',
                    name: $customerName !== '' ? $customerName : null,
                );

                if ($result['success'] ?? false) {
                    if ($result['duplicate'] ?? false) {
                        $duplicates++;
                        $this->newLine();
                        $this->comment("  Duplicate: User #{$user->id} already synced (ref: {$reference}).");
                    } else {
                        $success++;
                    }
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

        $this->info("Sync complete: {$success} synced, {$duplicates} duplicates, {$failed} failed, {$skipped} skipped.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
