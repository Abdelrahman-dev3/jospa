<?php

namespace App\Console\Commands;

use App\Models\Occasion;
use App\Models\OccasionSmsLog;
use App\Models\User;
use App\Services\TaqnyatSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendBirthdaySmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'occasions:send-birthdays {--force : Force sending even if disabled or already sent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated birthday greeting SMS to customers celebrating their birthday today';

    /**
     * Execute the console command.
     */
    public function handle(TaqnyatSmsService $smsService): int
    {
        $isEnabled = setting('birthday_sms_enabled', true);
        if (! $isEnabled && ! $this->option('force')) {
            $this->info('Birthday SMS automation is disabled in settings.');
            return 0;
        }

        $today = Carbon::today();
        $this->info("Checking birthdays for: {$today->format('m-d')}");

        // Find active customers with valid mobile and birthday today
        $customers = User::role('user')
            ->active()
            ->whereNotNull('date_of_birth')
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            ->get();

        if ($customers->isEmpty()) {
            $this->info('No customers found celebrating their birthday today.');
            return 0;
        }

        $defaultTemplate = 'عزيزتنا {name}، يسر فريق {app_name} أن يتمنى لكِ عيد ميلاد سعيد وكل عام وأنتِ بألف خير! بمناسبة يومكِ المميز، يسعدنا أن نهديكِ تجربة استرخاء لا تُنسى في فرعنا.';
        $template = setting('birthday_sms_template') ?: $defaultTemplate;

        // Find or create birthday campaign container
        $birthdayOccasion = Occasion::firstOrCreate(
            ['target_type' => 'birthday_automation'],
            [
                'name' => 'تهنئة أعياد ميلاد العملاء التلقائية',
                'description' => 'حملة إرسال رسائل تهنئة يومية تلقائية لعملاء المركز في أعياد ميلادهم',
                'target_type' => 'birthday_automation',
                'message_template' => $template,
                'status' => 'sent',
            ]
        );

        $this->info("Found {$customers->count()} birthday customer(s). Sending SMS...");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($customers as $customer) {
            // Check if customer already received a birthday SMS this year to prevent duplicates
            $alreadySent = OccasionSmsLog::where('user_id', $customer->id)
                ->where('status', 'sent')
                ->whereYear('created_at', $today->year)
                ->where(function ($q) use ($birthdayOccasion) {
                    $q->where('occasion_id', $birthdayOccasion->id)
                      ->orWhere('message', 'LIKE', '%عيد ميلاد%');
                })
                ->exists();

            if ($alreadySent && ! $this->option('force')) {
                $this->line("Skipped {$customer->full_name} (already sent this year).");
                continue;
            }

            $rawPhone = (string) $customer->mobile;
            $normalizedPhone = $smsService->validatePhoneNumber($rawPhone);

            if (! $normalizedPhone) {
                $failedCount++;
                OccasionSmsLog::create([
                    'occasion_id' => $birthdayOccasion->id,
                    'user_id' => $customer->id,
                    'customer_name' => $customer->full_name,
                    'phone' => $rawPhone,
                    'message' => $birthdayOccasion->replaceVariablesForUser($customer),
                    'status' => 'failed',
                    'response_data' => 'رقم الجوال غير صالح لتنسيق المملكة العربية السعودية',
                ]);
                continue;
            }

            $message = $birthdayOccasion->replaceVariablesForUser($customer);

            try {
                $response = $smsService->sendSms($normalizedPhone, $message);
                if ($response !== false) {
                    $sentCount++;
                    OccasionSmsLog::create([
                        'occasion_id' => $birthdayOccasion->id,
                        'user_id' => $customer->id,
                        'customer_name' => $customer->full_name,
                        'phone' => $normalizedPhone,
                        'message' => $message,
                        'status' => 'sent',
                        'response_data' => is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : (string) $response,
                    ]);
                    $this->info("✓ Sent to: {$customer->full_name} ({$normalizedPhone})");
                } else {
                    $failedCount++;
                    OccasionSmsLog::create([
                        'occasion_id' => $birthdayOccasion->id,
                        'user_id' => $customer->id,
                        'customer_name' => $customer->full_name,
                        'phone' => $normalizedPhone,
                        'message' => $message,
                        'status' => 'failed',
                        'response_data' => 'استجابة غير ناجحة من بوابة Taqnyat SMS',
                    ]);
                    $this->warn("✗ Failed for: {$customer->full_name}");
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Birthday SMS failed for user {$customer->id}: " . $e->getMessage());
            }
        }

        $birthdayOccasion->increment('sent_count', $sentCount);
        $birthdayOccasion->increment('failed_count', $failedCount);
        $birthdayOccasion->update([
            'sent_at' => Carbon::now(),
        ]);

        $this->info("Completed: {$sentCount} sent, {$failedCount} failed.");
        return 0;
    }
}
