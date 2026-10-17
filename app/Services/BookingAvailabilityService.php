<?php

namespace App\Services;

use Carbon\Carbon;

class BookingAvailabilityService
{
    /**
     * Check if booking window restriction is active.
     */
    public static function isRestrictionEnabled(): bool
    {
        return (bool) setting('booking_window_restriction', 0);
    }

    /**
     * Get window type: 'rolling', 'date_range', or 'both'.
     */
    public static function getWindowType(): string
    {
        return (string) setting('booking_window_type', 'rolling');
    }

    /**
     * Check if a given date is allowed for booking.
     *
     * @param string|\DateTimeInterface $date
     * @return bool
     */
    public static function isDateAllowed($date): bool
    {
        try {
            $target = $date instanceof \DateTimeInterface
                ? Carbon::instance($date)->startOfDay()
                : Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return false;
        }

        $today = Carbon::today();

        // Past dates are never allowed for new bookings
        if ($target->lt($today)) {
            return false;
        }

        // If restriction is not enabled, all future dates are open
        if (!static::isRestrictionEnabled()) {
            return true;
        }

        $range = static::getAllowedRange();
        $minDate = $range['min_date'];
        $maxDate = $range['max_date'];

        if ($minDate && $target->lt($minDate)) {
            return false;
        }

        if ($maxDate && $target->gt($maxDate)) {
            return false;
        }

        return true;
    }

    /**
     * Get the allowed date boundaries [min_date, max_date].
     *
     * @return array{min_date: Carbon, max_date: Carbon}
     */
    public static function getAllowedRange(): array
    {
        $today = Carbon::today();
        $type = static::getWindowType();

        $minDate = $today->copy();
        $maxDate = null;

        // 1) Rolling limit (months or days)
        if (in_array($type, ['rolling', 'both'], true)) {
            $months = (int) setting('booking_advance_months', 0);
            $days = (int) setting('booking_advance_days', 0);

            if ($months > 0) {
                $rollingMax = $today->copy()->addMonths($months);
            } elseif ($days > 0) {
                $rollingMax = $today->copy()->addDays($days);
            } else {
                // Default 2 months if rolling selected but empty
                $rollingMax = $today->copy()->addMonths(2);
            }

            $maxDate = $rollingMax;
        }

        // 2) Specific date range
        if (in_array($type, ['date_range', 'both'], true)) {
            $startDateStr = setting('booking_start_date');
            $endDateStr = setting('booking_end_date');

            if ($startDateStr) {
                try {
                    $customStart = Carbon::parse($startDateStr)->startOfDay();
                    if ($customStart->gt($minDate)) {
                        $minDate = $customStart;
                    }
                } catch (\Throwable $e) {
                }
            }

            if ($endDateStr) {
                try {
                    $customEnd = Carbon::parse($endDateStr)->startOfDay();
                    if ($maxDate === null || $customEnd->lt($maxDate)) {
                        $maxDate = $customEnd;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        // Fallback maxDate if none computed
        if ($maxDate === null) {
            $maxDate = $today->copy()->addMonths(2);
        }

        // Ensure maxDate is at least minDate
        if ($maxDate->lt($minDate)) {
            $maxDate = $minDate->copy();
        }

        return [
            'min_date' => $minDate,
            'max_date' => $maxDate,
        ];
    }

    /**
     * Get a list of allowed date strings (Y-m-d) within the active window.
     *
     * @param int|null $maxDays Max number of dates to generate
     * @return array<string>
     */
    public static function getAllowedDatesList(?int $maxDays = null): array
    {
        $range = static::getAllowedRange();
        $current = $range['min_date']->copy();
        $max = $range['max_date'];

        $dates = [];
        $limit = $maxDays ?? 365;
        $count = 0;

        while ($current->lte($max) && $count < $limit) {
            $dates[] = $current->toDateString();
            $current->addDay();
            $count++;
        }

        return $dates;
    }

    /**
     * Check if the entire booking schedule is currently closed for customers.
     * Returns true when restriction is ON and today is outside the allowed window.
     */
    public static function isScheduleClosed(): bool
    {
        if (!static::isRestrictionEnabled()) {
            return false;
        }

        $range = static::getAllowedRange();
        $today = Carbon::today();

        // Schedule is closed if today is before min_date or after max_date
        if ($today->lt($range['min_date']) || $today->gt($range['max_date'])) {
            return true;
        }

        return false;
    }

    /**
     * Get schedule closed info for the frontend page.
     */
    public static function getScheduleClosedInfo(): array
    {
        $range = static::getAllowedRange();
        $today = Carbon::today();
        $minDate = $range['min_date'];
        $maxDate = $range['max_date'];

        $opensAt = null;
        $closedSince = null;

        if ($today->lt($minDate)) {
            $opensAt = $minDate->toDateString();
        } elseif ($today->gt($maxDate)) {
            $closedSince = $maxDate->toDateString();
        }

        return [
            'opens_at'     => $opensAt,
            'closed_since' => $closedSince,
            'min_date'     => $minDate->toDateString(),
            'max_date'     => $maxDate->toDateString(),
        ];
    }

    /**
     * Localized rejection message when a user tries to book an invalid date.
     */
    public static function getRejectionMessage($date = null): string
    {
        $range = static::getAllowedRange();
        $minStr = $range['min_date']->toDateString();
        $maxStr = $range['max_date']->toDateString();

        if (app()->getLocale() === 'ar') {
            return "عفواً، الحجوزات غير متاحة في هذا التاريخ. الحجز متاح فقط خلال الفترة من {$minStr} إلى {$maxStr}.";
        }

        return "Sorry, bookings are not available on this date. Booking is allowed only between {$minStr} and {$maxStr}.";
    }

    /**
     * Payload for mobile app / API configuration.
     */
    public static function getConfigurationPayload(): array
    {
        $range = static::getAllowedRange();

        return [
            'restriction_enabled' => static::isRestrictionEnabled(),
            'window_type' => static::getWindowType(),
            'advance_months' => (int) setting('booking_advance_months', 2),
            'advance_days' => (int) setting('booking_advance_days', 60),
            'start_date' => setting('booking_start_date'),
            'end_date' => setting('booking_end_date'),
            'min_booking_date' => $range['min_date']->toDateString(),
            'max_booking_date' => $range['max_date']->toDateString(),
        ];
    }
}
