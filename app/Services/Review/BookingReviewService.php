<?php

namespace App\Services\Review;

use App\Models\BookingReview;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyPointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\OdooLoyaltyService;
use App\Services\UserNotificationService;
use App\Services\WhatsApp\JavnaWhatsAppService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;

/**
 * Post-service review flow: request (WhatsApp template) -> 3 interactive questions
 * (WhatsApp or website form) -> one saved review -> Google Maps click -> cashback.
 *
 * Idempotency rests on: one row per booking (unique booking_id), a row lock around
 * completion, the WalletHistory keyed by booking_review_id, so retries/redeliveries
 * can never duplicate a review or its cashback.
 */
class BookingReviewService
{
    public const CASHBACK_AMOUNT = 50.0;
    public const WALLET_SOURCE = 'booking_review';
    public const POINTS_SETTING_KEY = 'review_loyalty_points';
    public const LOYALTY_SOURCE = 'booking_review';

    public function __construct(private JavnaWhatsAppService $whatsApp)
    {
    }

    public function cashbackAmount(): float
    {
        return (float) Setting::get('review_wallet_cashback', self::CASHBACK_AMOUNT);
    }

    public const GOOGLE_MAPS_DEFAULT_KEY = 'review_google_maps_url';
    public const GOOGLE_MAPS_FALLBACK_URL = 'https://www.google.com/maps/place/%D8%AC%D9%88+%D8%B3%D8%A8%D8%A7%E2%80%AD/@24.7501491,46.6261178,16.93z/data=!4m8!3m7!1s0x3e2ee3b0cf2cf4b3:0xd9fc022701b9d604!8m2!3d24.7501398!4d46.6236843!9m1!1b1!16s%2Fg%2F11shgfzh4g?entry=ttu&g_ep=EgoyMDI2MTAyOC4wIKXMDSoASAFQAw%3D%3D';

    public static function googleMapsBranchKey(int $branchId): string
    {
        return "review_google_maps_url_branch_{$branchId}";
    }

    /** Google Maps review link for the branch the customer visited, else the default link. */
    public function googleMapsUrl(BookingReview $review): string
    {
        if ($review->branch_id) {
            $url = trim((string) Setting::get(self::googleMapsBranchKey((int) $review->branch_id), ''));
            if ($url !== '') {
                return $url;
            }
        }

        $default = trim((string) Setting::get(self::GOOGLE_MAPS_DEFAULT_KEY, ''));

        return $default !== '' ? $default : self::GOOGLE_MAPS_FALLBACK_URL;
    }

    public function pointsPerReview(): int
    {
        return max(0, (int) Setting::get(self::POINTS_SETTING_KEY, 0));
    }

    /* ------------------------------------------------------------------ *
     | 1. Review request after the service
     * ------------------------------------------------------------------ */

    /**
     * Send the review invitation once per booking. Throws when the WhatsApp send fails so the
     * queue retries; request_sent_at is only set on success, so a retry never double-sends.
     */
    public function sendRequest(Booking $booking): ?BookingReview
    {
        $booking->loadMissing('user');
        $user = $booking->user;

        if (! $user || blank($user->mobile) || in_array($booking->status, ['cancelled', 'canceled'], true)) {
            return null;
        }

        return Cache::lock("booking_review_request_{$booking->id}", 60)->block(10, function () use ($booking, $user) {
            $review = $this->firstOrCreateForBooking($booking);

            if ($review->isCompleted() || $review->request_sent_at) {
                return $review;
            }

            $review->increment('request_attempts');

            $template = (string) config('services.javna.whatsapp_review_request_template_name', 'jospa_review_request');
            $sent = $this->whatsApp->sendTemplate($user->mobile, [], $template, 'ar');

            if (! $sent) {
                throw new \RuntimeException("Failed to send review request for booking #{$booking->id}.");
            }

            $review->update([
                'request_sent_at' => now(),
                'request_message_id' => $this->whatsApp->getLastAcceptedMessage()['message_id'] ?? null,
            ]);

            app(UserNotificationService::class)->notifyReviewRequested($user, $review);

            return $review;
        });
    }

    public function firstOrCreateForBooking(Booking $booking): BookingReview
    {
        $existing = BookingReview::where('booking_id', $booking->id)->first();
        if ($existing) {
            return $existing;
        }

        $firstService = BookingService::where('booking_id', $booking->id)->orderBy('id')->first();

        try {
            return BookingReview::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'branch_id' => $booking->branch_id,
                'employee_id' => $firstService->employee_id ?? null,
                'service_id' => $firstService->service_id ?? null,
                'token' => Str::random(40),
                'status' => BookingReview::STATUS_REQUESTED,
            ]);
        } catch (QueryException $e) {
            // Lost a race with a concurrent request: the unique booking_id guarantees one row.
            return BookingReview::where('booking_id', $booking->id)->firstOrFail();
        }
    }

    /* ------------------------------------------------------------------ *
     | 2. WhatsApp conversation
     * ------------------------------------------------------------------ */

    /**
     * Handle a customer's inbound WhatsApp message. Returns true when it belonged to a review
     * conversation (so the caller knows it was consumed).
     */
    public function handleInbound(string $phone, ?string $messageId, ?string $replyId, ?string $replyText): bool
    {
        $review = $this->findActiveReviewByPhone($phone);
        if (! $review) {
            Log::info('Review reply ignored: no active review request for this phone.', [
                'phone' => $phone,
                'reply_text' => $replyText,
            ]);

            return false;
        }

        if ($messageId && ! Cache::add("review_inbound_{$messageId}", 1, now()->addDay())) {
            return true; // webhook redelivery of a message we already processed
        }

        $reviewId = $review->id;
        $outcome = null; // ['send_step' => int|null, 'complete' => bool, 'resend' => bool]

        DB::transaction(function () use ($reviewId, $messageId, $replyId, $replyText, &$outcome) {
            $review = BookingReview::lockForUpdate()->find($reviewId);
            if (! $review || ! $review->isAnswerable()) {
                return;
            }

            if ($review->status === BookingReview::STATUS_REQUESTED) {
                if (! $this->looksLikeReviewStart($replyId, $replyText)) {
                    Log::info('Review reply ignored: not recognised as the start button.', [
                        'review_id' => $review->id,
                        'reply_id' => $replyId,
                        'reply_text' => $replyText,
                    ]);

                    return;
                }
                $review->update([
                    'status' => BookingReview::STATUS_IN_PROGRESS,
                    'current_step' => 1,
                    'last_inbound_message_id' => $messageId,
                ]);
                $outcome = ['send_step' => 1];

                return;
            }

            $step = (int) $review->current_step;
            $answer = $this->parseAnswer($review, $step, $replyId, $replyText);

            if ($answer === false) {
                return; // reply to an older list: ignore
            }
            if ($answer === null) {
                $outcome = ['send_step' => $step]; // not understood: ask the same question again

                return;
            }

            $question = ReviewQuestions::question($step);
            $review->fill([$question['field'] => $answer, 'last_inbound_message_id' => $messageId]);

            if ($step >= ReviewQuestions::totalSteps()) {
                $review->save();
                $outcome = ['complete' => true];

                return;
            }

            $review->current_step = $step + 1;
            $review->save();
            $outcome = ['send_step' => $step + 1];
        });

        $review = $review->fresh();

        if (($outcome['complete'] ?? false) === true) {
            $this->complete($review, ReviewQuestions::SOURCE_WHATSAPP);
        } elseif (isset($outcome['send_step'])) {
            $this->sendQuestion($review, (int) $outcome['send_step']);
        }

        return true;
    }

    public function sendQuestion(BookingReview $review, int $step): bool
    {
        $question = ReviewQuestions::question($step);
        $mobile = $review->user->mobile ?? null;
        if (! $question || blank($mobile)) {
            return false;
        }

        $rows = [];
        foreach ($question['options'] as $value => $label) {
            $rows[] = ['id' => "rv:{$review->id}:{$step}:{$value}", 'title' => $label];
        }

        $body = "({$step}/" . ReviewQuestions::totalSteps() . ") {$question['text']}";

        // Preferred mode: one approved template per question, each with 4 quick-reply buttons.
        if (config('services.javna.whatsapp_review_question_mode', 'template') === 'template') {
            $templateName = (string) config("services.javna.whatsapp_review_question_{$step}_template_name");
            if ($templateName !== '' && $this->whatsApp->sendTemplate($mobile, [], $templateName, 'ar')) {
                return true;
            }
        }

        if ($this->whatsApp->sendInteractiveList($mobile, $body, 'اختاري الإجابة', $rows)) {
            return true;
        }

        // Interactive delivery failed (outside the 24h window / provider issue): numbered fallback.
        $lines = [];
        $position = 1;
        foreach ($question['options'] as $label) {
            $lines[] = "{$position} - {$label}";
            $position++;
        }

        return $this->whatsApp->sendText($mobile, $body . "\n\n" . implode("\n", $lines) . "\n\nأرسلي رقم الإجابة من 1 إلى " . count($lines));
    }

    /**
     * @return int|null|false rating 1-4, null when not understood, false when it answers another step
     */
    private function parseAnswer(BookingReview $review, int $step, ?string $replyId, ?string $replyText): int|null|false
    {
        if ($replyId && preg_match('/^rv:(\d+):(\d+):([1-4])$/', trim($replyId), $m)) {
            if ((int) $m[1] !== $review->id || (int) $m[2] !== $step) {
                return false;
            }

            return (int) $m[3];
        }

        $text = trim((string) $replyText);
        $options = ReviewQuestions::question($step)['options'];

        if (preg_match('/^[1-4]$/', $text)) {
            return 5 - (int) $text; // "1" = best option, as listed in the numbered fallback
        }

        $normalized = $this->normalizeLabel($text);
        foreach ($options as $value => $label) {
            if ($normalized !== '' && $normalized === $this->normalizeLabel($label)) {
                return (int) $value;
            }
        }

        return null;
    }

    /** True when a free-text value is the start button or one of the answer labels. */
    public function isReviewText(string $text): bool
    {
        if ($this->looksLikeReviewStart(null, $text)) {
            return true;
        }

        $normalized = $this->normalizeLabel($text);
        if ($normalized === '') {
            return false;
        }

        foreach (ReviewQuestions::all() as $question) {
            foreach ($question['options'] as $label) {
                if ($normalized === $this->normalizeLabel($label)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function looksLikeReviewStart(?string $replyId, ?string $replyText): bool
    {
        if ($replyId && str_starts_with(trim($replyId), 'rv:')) {
            return true;
        }

        $text = $this->normalizeLabel((string) $replyText);

        return str_contains($text, 'قيم') && str_contains($text, 'تجرب');
    }

    private function normalizeLabel(string $text): string
    {
        $text = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '', $text) ?? '';
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text) ?? $text; // tashkeel / tatweel
        $text = str_replace(['أ', 'إ', 'آ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ي', 'ه'], $text);

        return mb_strtolower($text);
    }

    private function findActiveReviewByPhone(string $phone): ?BookingReview
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 8) {
            return null;
        }

        $tail = substr($digits, -9);
        $userIds = User::whereRaw("RIGHT(REPLACE(REPLACE(mobile, ' ', ''), '+', ''), 9) = ?", [$tail])->pluck('id');
        if ($userIds->isEmpty()) {
            return null;
        }

        return BookingReview::with('user')
            ->whereIn('user_id', $userIds)
            ->whereIn('status', [BookingReview::STATUS_REQUESTED, BookingReview::STATUS_IN_PROGRESS])
            ->whereNotNull('request_sent_at')
            ->where('request_sent_at', '>', now()->subDays(BookingReview::WINDOW_DAYS))
            ->latest('request_sent_at')
            ->first();
    }

    /* ------------------------------------------------------------------ *
     | 3. Website form
     * ------------------------------------------------------------------ */

    /** @param array<string, int|string> $answers keyed by overall_rating / specialist_rating / service_rating */
    public function submitFromWebsite(BookingReview $review, array $answers): bool
    {
        $values = [];
        foreach (ReviewQuestions::all() as $question) {
            $value = (int) ($answers[$question['field']] ?? 0);
            if ($value < 1 || $value > 4) {
                throw new \InvalidArgumentException('Invalid rating value.');
            }
            $values[$question['field']] = $value;
        }

        return $this->complete($review, ReviewQuestions::SOURCE_WEBSITE, $values) !== null;
    }

    /* ------------------------------------------------------------------ *
     | 4. Completion: save answers, do NOT award cashback yet
     * ------------------------------------------------------------------ */

    /**
     * Finalize a review. Returns the review when THIS call completed it, null when it was
     * already completed (or no longer answerable) — so side effects run exactly once.
     * Cashback is NOT awarded here — it is awarded only after the customer clicks Google Maps.
     */
    public function complete(BookingReview $review, string $source, array $answers = []): ?BookingReview
    {
        $completed = DB::transaction(function () use ($review, $source, $answers) {
            $locked = BookingReview::lockForUpdate()->find($review->id);
            if (! $locked || $locked->isCompleted()) {
                return null;
            }

            $locked->fill($answers);

            foreach (ReviewQuestions::all() as $question) {
                if (! $locked->{$question['field']}) {
                    return null; // never complete a review with missing answers
                }
            }

            $locked->fill([
                'status'       => BookingReview::STATUS_COMPLETED,
                'source'       => $source,
                'current_step' => ReviewQuestions::totalSteps(),
                'completed_at' => now(),
            ]);

            $locked->save();

            return $locked;
        });

        if (! $completed) {
            return null;
        }

        $this->afterCompletion($completed);

        return $completed;
    }

    /* ------------------------------------------------------------------ *
     | 5. Google Maps confirmation: award cashback once the customer clicks
     * ------------------------------------------------------------------ */

    /**
     * Called when the customer clicks the Google Maps link. Awards 50 SAR cashback
     * once and sends the WhatsApp thank-you message. Safe to retry: idempotency via
     * google_maps_clicked_at and WalletHistory booking_review_id uniqueness.
     *
     * Returns true when cashback was awarded, false when already done or review not completed.
     */
    public function confirmGoogleReview(BookingReview $review): bool
    {
        if (! $review->isCompleted() || $review->google_maps_clicked_at) {
            return false;
        }

        $awardedCashback = 0.0;

        DB::transaction(function () use ($review, &$awardedCashback) {
            $locked = BookingReview::lockForUpdate()->find($review->id);
            if (! $locked || ! $locked->isCompleted() || $locked->google_maps_clicked_at) {
                return;
            }

            $cashback = $this->cashbackAmount();
            if ($cashback > 0 && $this->awardCashback($locked, $cashback)) {
                $locked->points_awarded    = (int) $cashback;
                $locked->points_awarded_at = now();
                $awardedCashback           = $cashback;
            }

            $locked->google_maps_clicked_at = now();
            $locked->save();
        });

        if ($awardedCashback > 0) {
            $review->refresh();
            $this->afterGoogleConfirm($review, $awardedCashback);
        }

        return true;
    }

    /** Deposits cashback into the customer's wallet once per review. */
    private function awardCashback(BookingReview $review, float $amount): bool
    {
        if ($amount <= 0 || ! $review->user_id) {
            return false;
        }

        $already = \Modules\Wallet\Models\WalletHistory::where('user_id', $review->user_id)
            ->where('activity_type', 'deposit')
            ->where('activity_data->booking_review_id', $review->id)
            ->exists();

        if ($already) {
            return false;
        }

        $user = User::find($review->user_id);
        if (! $user) {
            return false;
        }

        $wallet = \Modules\Wallet\Models\Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'amount' => 0,
                'title' => trim((string) ($user->first_name . ' ' . ($user->last_name ?? ''))),
                'status' => 1,
            ]
        );

        $wallet->increment('amount', $amount);

        \Modules\Wallet\Models\WalletHistory::create([
            'datetime'         => now(),
            'user_id'          => $user->id,
            'activity_type'    => 'deposit',
            'activity_message' => 'كاش باك تقييم تجربة Jo Spa (' . number_format($amount, 2) . ' ريال)',
            'activity_data'    => json_encode([
                'booking_review_id'   => $review->id,
                'booking_id'          => $review->booking_id,
                'credit_debit_amount' => $amount,
                'source'              => self::WALLET_SOURCE,
            ]),
        ]);

        return true;
    }

    private function afterCompletion(BookingReview $review): void
    {
        $user = User::find($review->user_id);
        if (! $user) {
            return;
        }

        app(UserNotificationService::class)->notifyReviewReceived($user, $review);

        // Send WhatsApp message asking the customer to click Google Maps link to activate cashback
        try {
            $mobile = $user->mobile ?? null;
            if ($mobile) {
                $confirmUrl = route('booking-review.google-confirm', $review->token);
                $lines = [
                    'شكرًا لإجابتكِ على أسئلة التقييم 🤍',
                    '',
                    'خطوة أخيرة لتفعيل 50 ريال كاش باك:',
                    'قيّمينا على Google Maps من خلال الرابط التالي:',
                    '',
                    $confirmUrl,
                    '',
                    'بمجرد الضغط على الرابط، ستُضاف 50 ريال فورًا إلى محفظتكِ ✨',
                ];
                $this->whatsApp->sendText($mobile, implode("\n", $lines));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send Google Maps review link.', ['review_id' => $review->id, 'message' => $e->getMessage()]);
        }
    }

    private function afterGoogleConfirm(BookingReview $review, float $awardedCashback): void
    {
        $user = User::find($review->user_id);
        if (! $user) {
            return;
        }

        $notifier = app(UserNotificationService::class);
        if ($awardedCashback > 0) {
            $notifier->notifyWalletTopUp($user, $awardedCashback);
        }

        try {
            \App\Jobs\SendReviewConfirmationWhatsAppJob::dispatch($review->id);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch review confirmation job.', ['review_id' => $review->id, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Thank-you WhatsApp message. Only sent after Google Maps click. Safe to retry.
     */
    public function sendConfirmation(BookingReview $review): bool
    {
        $review->refresh();
        $mobile = $review->user->mobile ?? null;

        if (! $review->isCompleted() || ! $review->google_maps_clicked_at || $review->confirmation_sent_at || blank($mobile)) {
            return true;
        }

        $lines = ['تم استلام تقييمكِ بنجاح 🤍', ''];
        $cashback = (float) ($review->points_awarded ?: $this->cashbackAmount());
        if ($cashback > 0) {
            $lines[] = 'تمت إضافة ' . (int) $cashback . ' ريال كاش باك إلى محفظتكِ فورًا! ✨';
            $lines[] = '';
        }
        $lines[] = 'شكرًا لثقتكِ بنا، نسعد دائمًا بأن نكون جزءًا من لحظات دلالكِ 🤍';

        if (! $this->whatsApp->sendText($mobile, implode("\n", $lines))) {
            return false;
        }

        $review->update(['confirmation_sent_at' => now()]);

        return true;
    }
}
