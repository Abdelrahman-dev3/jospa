<?php

namespace App\Console\Commands;

use App\Models\Occasion;
use App\Models\OccasionSmsLog;
use App\Models\User;
use App\Services\TaqnyatSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRecurringOccasionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'occasions:send-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated SMS for recurring occasions (e.g. Holidays) matching today\'s date';

    /**
     * Execute the console command.
     */
    public function handle(TaqnyatSmsService $smsService)
    {
        $today = Carbon::today();
        $this->info("Checking recurring occasions for: {$today->format('m-d')}");

        $occasions = Occasion::where('is_recurring', true)
            ->whereMonth('occasion_date', $today->month)
            ->whereDay('occasion_date', $today->day)
            ->get();

        if ($occasions->isEmpty()) {
            $this->info('No recurring occasions found for today.');
            return 0;
        }

        foreach ($occasions as $occasion) {
            $this->info("Processing occasion: {$occasion->name}");

            // Find target customers
            if ($occasion->target_type === 'specific') {
                $customers = User::where('id', $occasion->user_id)
                    ->whereNotNull('mobile')
                    ->where('mobile', '!=', '')
                    ->get();
            } elseif ($occasion->target_type === 'birthday') {
                $customers = User::isCustomer()
                    ->active()
                    ->whereNotNull('date_of_birth')
                    ->whereNotNull('mobile')
                    ->where('mobile', '!=', '')
                    ->whereMonth('date_of_birth', $today->month)
                    ->whereDay('date_of_birth', $today->day)
                    ->get();
            } else {
                $customers = User::isCustomer()
                    ->active()
                    ->whereNotNull('mobile')
                    ->where('mobile', '!=', '')
                    ->get();
            }

            if ($customers->isEmpty()) {
                $this->info("No valid customers found for occasion {$occasion->name}.");
                continue;
            }

            $sentCount = 0;
            $failedCount = 0;

            foreach ($customers as $customer) {
                // Prevent duplicate sending for the same occasion this year
                $alreadySent = OccasionSmsLog::where('user_id', $customer->id)
                    ->where('occasion_id', $occasion->id)
                    ->where('status', 'sent')
                    ->whereYear('created_at', $today->year)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $rawPhone = (string) $customer->mobile;
                $normalizedPhone = $smsService->validatePhoneNumber($rawPhone);

                if (! $normalizedPhone) {
                    $failedCount++;
                    OccasionSmsLog::create([
                        'occasion_id' => $occasion->id,
                        'user_id' => $customer->id,
                        'customer_name' => $customer->full_name,
                        'phone' => $rawPhone,
                        'message' => $occasion->replaceVariablesForUser($customer),
                        'status' => 'failed',
                        'response_data' => 'رقم الجوال غير صالح لتنسيق المملكة العربية السعودية',
                    ]);
                    continue;
                }

                $message = $occasion->replaceVariablesForUser($customer);

                try {
                    $response = $smsService->sendSms($normalizedPhone, $message);
                    if ($response !== false) {
                        $sentCount++;
                        OccasionSmsLog::create([
                            'occasion_id' => $occasion->id,
                            'user_id' => $customer->id,
                            'customer_name' => $customer->full_name,
                            'phone' => $normalizedPhone,
                            'message' => $message,
                            'status' => 'sent',
                            'response_data' => is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : (string) $response,
                        ]);
                    } else {
                        $failedCount++;
                        OccasionSmsLog::create([
                            'occasion_id' => $occasion->id,
                            'user_id' => $customer->id,
                            'customer_name' => $customer->full_name,
                            'phone' => $normalizedPhone,
                            'message' => $message,
                            'status' => 'failed',
                            'response_data' => 'استجابة غير ناجحة من بوابة SMS',
                        ]);
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Recurring Occasion SMS failed: " . $e->getMessage());
                }
            }

            $occasion->increment('sent_count', $sentCount);
            $occasion->increment('failed_count', $failedCount);
            $occasion->update(['sent_at' => Carbon::now(), 'status' => 'sent']);

            $this->info("Completed {$occasion->name}: {$sentCount} sent, {$failedCount} failed.");
        }

        return 0;
    }
}
