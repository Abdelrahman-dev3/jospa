<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Booking\Models\BookingCart;
use Carbon\Carbon;

class CleanupCartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cart:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove cart items older than 24 hours';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $expirationTime = Carbon::now()->subHours(24);

        $count = BookingCart::where('created_at', '<', $expirationTime)->delete();

        $this->info("Deleted {$count} old cart items.");

        return 0;
    }
}
