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
 * (WhatsApp or website form) -> one saved review -> loyalty points -> confirmation.
 *
 * Idempotency rests on: one row per booking (unique booking_id), a row lock around
 * completion, the loyalty transaction keyed by (source, source_id) and deterministic
 * Odoo references, so retries/redeliveries can never duplicate a review or its points.
 */
class BookingReviewService
{
    public const POINTS_SETTING_KEY = 'review_loyalty_points';
    public const LOYALTY_SOURCE = 'booking_review';

    public function __construct(private JavnaWhatsAppService $whatsApp)
    {
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
     | 4. Completion: save once, award points once
     * ------------------------------------------------------------------ */

    /**
     * Finalize a review. Returns the review when THIS call completed it, null when it was
     * already completed (or no longer answerable) — so side effects run exactly once.
     */
    public function complete(BookingReview $review, string $source, array $answers = []): ?BookingReview
    {
        $awardedPoints = 0;

        $completed = DB::transaction(function () use ($review, $source, $answers, &$awardedPoints) {
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
                'status' => BookingReview::STATUS_COMPLETED,
                'source' => $source,
                'current_step' => ReviewQuestions::totalSteps(),
                'completed_at' => now(),
            ]);

            $points = $this->pointsPerReview();
            if ($points > 0 && $this->awardPoints($locked, $points)) {
                $locked->points_awarded = $points;
                $locked->points_awarded_at = now();
                $awardedPoints = $points;
            }

            $locked->save();

            return $locked;
        });

        if (! $completed) {
            return null;
        }

        $this->afterCompletion($completed, $awardedPoints);

        return $completed;
    }

    /** Runs inside the completion transaction. Returns false if points were already granted. */
    private function awardPoints(BookingReview $review, int $points): bool
    {
        $already = LoyaltyPointTransaction::where('source', self::LOYALTY_SOURCE)
            ->where('source_id', $review->id)
            ->exists();
        if ($already) {
            return false;
        }

        $loyalty = LoyaltyPoint::where('user_id', $review->user_id)->lockForUpdate()->first()
            ?? new LoyaltyPoint(['user_id' => $review->user_id, 'points' => 0]);
        $loyalty->points = ($loyalty->points ?? 0) + $points;
        $loyalty->save();

        LoyaltyPointTransaction::create([
            'user_id' => $review->user_id,
            'action' => 'add',
            'points' => $points,
            'balance_after' => $loyalty->points,
            'source' => self::LOYALTY_SOURCE,
            'source_id' => $review->id,
        ]);

        return true;
    }

    private function afterCompletion(BookingReview $review, int $awardedPoints): void
    {
        $user = User::find($review->user_id);
        if (! $user) {
            return;
        }

        $notifier = app(UserNotificationService::class);
        $notifier->notifyReviewReceived($user, $review);

        if ($awardedPoints > 0) {
            $notifier->notifyLoyaltyPointsAdded($user, $awardedPoints);
            $this->reportPointsToOdoo($user, $review, $awardedPoints);
        }

        try {
            \App\Jobs\SendReviewConfirmationWhatsAppJob::dispatch($review->id);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch review confirmation job.', ['review_id' => $review->id, 'message' => $e->getMessage()]);
        }
    }

    private function reportPointsToOdoo(User $user, BookingReview $review, int $points): void
    {
        if (blank($user->mobile)) {
            return;
        }

        try {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            app(OdooLoyaltyService::class)->adjust(
                phone: $user->mobile,
                operation: 'add',
                points: $points,
                reference: 'WEB-REVIEW-' . $review->id, // deterministic: a retry cannot add twice
                note: 'Points earned for booking review #' . $review->booking_id,
                name: $name !== '' ? $name : null,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to report review points to Odoo.', ['review_id' => $review->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Thank-you WhatsApp message. Safe to retry: confirmation_sent_at is the idempotency flag.
     */
    public function sendConfirmation(BookingReview $review): bool
    {
        $review->refresh();
        $mobile = $review->user->mobile ?? null;

        if (! $review->isCompleted() || $review->confirmation_sent_at || blank($mobile)) {
            return true;
        }

        $lines = ['تم استلام تقييمكِ بنجاح 🤍', ''];
        $lines[] = $review->points_awarded > 0
            ? 'شكرًا لوقتكِ ومشاركتنا رأيكِ، تمت إضافة نقاط التقييم إلى رصيدكِ ✨'
            : 'شكرًا لوقتكِ ومشاركتنا رأيكِ ✨';
        $lines[] = '';
        $lines[] = 'نسعد بكِ دائمًا في عالم Jo Spa 🤍';

        if (! $this->whatsApp->sendText($mobile, implode("\n", $lines))) {
            return false;
        }

        $review->update(['confirmation_sent_at' => now()]);

        return true;
    }
}
