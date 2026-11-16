<?php

namespace App\Services;

use Carbon\Carbon;
use Modules\Category\Models\Category;
use Modules\Service\Models\Service;

/**
 * Booking window per service or category (e.g. Massage only at night): restricts the
 * hours in which a service may be booked, independent of staff hours.
 *
 * Resolution order: the service itself, then its sub-category, then its category,
 * then that category's parent. The first one with both times set wins. Windows are within a
 * single day (start < end), consistent with staff/branch working hours.
 */
class CategoryBookingHoursService
{
    /** @var array<int, array{start:string,end:string}|null> */
    private static array $cache = [];

    /**
     * @return array{start:string,end:string}|null  Times as "H:i", or null when unrestricted.
     */
    public static function windowForService(int $serviceId): ?array
    {
        if ($serviceId <= 0) {
            return null;
        }

        if (array_key_exists($serviceId, self::$cache)) {
            return self::$cache[$serviceId];
        }

        $service = Service::query()
            ->select(['id', 'category_id', 'sub_category_id', 'booking_start_time', 'booking_end_time'])
            ->find($serviceId);

        // The service's own window wins over any category window.
        $window = $service ? self::normalize($service->booking_start_time, $service->booking_end_time) : null;

        if ($service && ! $window) {
            $categoryIds = array_values(array_filter([
                (int) $service->sub_category_id,
                (int) $service->category_id,
            ]));

            $categories = $categoryIds
                ? Category::query()->whereIn('id', $categoryIds)
                    ->get(['id', 'parent_id', 'booking_start_time', 'booking_end_time'])
                    ->keyBy('id')
                : collect();

            $chain = [];
            foreach ($categoryIds as $id) {
                if ($categories->has($id)) {
                    $chain[] = $categories->get($id);
                }
            }

            $parentId = (int) ($categories->get((int) $service->category_id)?->parent_id ?? 0);
            if ($parentId > 0 && ! $categories->has($parentId)) {
                $parent = Category::query()->find($parentId, ['id', 'parent_id', 'booking_start_time', 'booking_end_time']);
                if ($parent) {
                    $chain[] = $parent;
                }
            }

            foreach ($chain as $category) {
                $window = self::normalize($category->booking_start_time, $category->booking_end_time);
                if ($window) {
                    break;
                }
            }
        }

        return self::$cache[$serviceId] = $window;
    }

    /**
     * Narrows a working window ("H:i" strings) to the service's category window.
     *
     * @return array{start:string,end:string}|null  null when the two windows do not overlap.
     */
    public static function clampWorkingWindow(int $serviceId, string $start, string $end): ?array
    {
        $window = self::windowForService($serviceId);
        if (! $window) {
            return ['start' => $start, 'end' => $end];
        }

        $clampedStart = max(self::toMinutes($start), self::toMinutes($window['start']));
        $clampedEnd = min(self::toMinutes($end), self::toMinutes($window['end']));

        if ($clampedEnd <= $clampedStart) {
            return null;
        }

        return ['start' => self::fromMinutes($clampedStart), 'end' => self::fromMinutes($clampedEnd)];
    }

    /** True when the whole appointment [start, start+duration] lies inside the category window. */
    public static function allows(int $serviceId, Carbon $startDateTime, int $durationMinutes): bool
    {
        $window = self::windowForService($serviceId);
        if (! $window) {
            return true;
        }

        $start = $startDateTime->hour * 60 + $startDateTime->minute;
        $end = $start + max(1, $durationMinutes);

        return $start >= self::toMinutes($window['start']) && $end <= self::toMinutes($window['end']);
    }

    public static function rejectionMessage(int $serviceId): string
    {
        $window = self::windowForService($serviceId);
        $range = $window
            ? Carbon::createFromFormat('H:i', $window['start'])->format('h:i A') . ' - ' . Carbon::createFromFormat('H:i', $window['end'])->format('h:i A')
            : '';

        return app()->getLocale() === 'ar'
            ? 'هذه الخدمة متاحة للحجز فقط من ' . $range . '، يرجى اختيار موعد آخر.'
            : 'This service can only be booked between ' . $range . '. Please choose another time.';
    }

    private static function normalize($start, $end): ?array
    {
        if (! $start || ! $end) {
            return null;
        }

        $startMinutes = self::toMinutes((string) $start);
        $endMinutes = self::toMinutes((string) $end);

        if ($endMinutes <= $startMinutes) {
            return null;
        }

        return ['start' => self::fromMinutes($startMinutes), 'end' => self::fromMinutes($endMinutes)];
    }

    private static function toMinutes(string $time): int
    {
        $parts = explode(':', trim($time));

        return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
    }

    private static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
