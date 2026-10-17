<?php

namespace Modules\Booking\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;
use Spatie\Activitylog\Models\Activity;

class BookingActivityLogger
{
    /**
     * Translated Arabic labels for booking statuses.
     */
    public static array $statusLabels = [
        'pending'   => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'check_in'  => 'تم الحضور',
        'checkout'  => 'أنهى الخدمة',
        'cancelled' => 'ملغي',
        'completed' => 'مكتمل',
    ];

    /**
     * Status color classes for badges.
     */
    public static array $statusColors = [
        'pending'   => 'warning',
        'confirmed' => 'primary',
        'check_in'  => 'info',
        'checkout'  => 'secondary',
        'cancelled' => 'danger',
        'completed' => 'success',
    ];

    /**
     * Status icons.
     */
    public static array $statusIcons = [
        'pending'   => 'fa-solid fa-clock',
        'confirmed' => 'fa-solid fa-circle-check',
        'check_in'  => 'fa-solid fa-user-check',
        'checkout'  => 'fa-solid fa-right-from-bracket',
        'cancelled' => 'fa-solid fa-ban',
        'completed' => 'fa-solid fa-flag-checkered',
    ];

    /**
     * Get a human-readable Arabic status label.
     */
    public static function getStatusLabel(?string $status): string
    {
        if (!$status) {
            return '-';
        }
        return self::$statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Get current authenticated user or fallback.
     */
    protected static function resolveCauser(?User $causer = null): ?User
    {
        if ($causer) {
            return $causer;
        }
        if (Auth::check()) {
            return Auth::user();
        }
        return null;
    }

    /**
     * Log booking creation.
     */
    public static function logCreated(Booking $booking, ?User $causer = null, array $extra = []): void
    {
        try {
            $causer = self::resolveCauser($causer) ?? $booking->createdBy ?? $booking->user;
            $causerName = $causer ? ($causer->full_name ?? $causer->name ?? $causer->first_name) : 'العميل';

            // Gather services details
            $booking->loadMissing(['services.employee', 'user', 'branch', 'bookingPackages']);

            $servicesList = [];
            foreach ($booking->services as $s) {
                $employeeName = $s->employee ? ($s->employee->full_name ?? $s->employee->name) : 'غير محدد';
                $serviceName = $s->service_name ?? ($s->service ? $s->service->name : 'خدمة');
                $servicesList[] = [
                    'name' => $serviceName,
                    'employee' => $employeeName,
                    'price' => $s->service_price ?? 0,
                    'duration' => $s->duration_min ?? 0,
                ];
            }

            $packagesList = [];
            foreach ($booking->bookingPackages as $bp) {
                $packagesList[] = [
                    'name' => $bp->name ?? 'حزمة',
                    'price' => $bp->package_price ?? 0,
                ];
            }

            $properties = array_merge([
                'action' => 'created',
                'customer_id' => $booking->user_id,
                'customer_name' => $booking->user ? ($booking->user->full_name ?? $booking->user->name) : '-',
                'customer_phone' => $booking->user->mobile ?? '-',
                'branch_id' => $booking->branch_id,
                'branch_name' => $booking->branch->name ?? '-',
                'start_date_time' => $booking->start_date_time ? Carbon::parse($booking->start_date_time)->format('Y-m-d H:i') : null,
                'status' => $booking->status,
                'status_label' => self::getStatusLabel($booking->status),
                'services' => $servicesList,
                'packages' => $packagesList,
                'note' => $booking->note,
                'payment_status' => $booking->payment_status ?? 0,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $extra);

            $description = "تم إنشاء الحجز بنجاح بواسطة " . $causerName;

            $activity = activity('booking')
                ->performedOn($booking)
                ->event('created')
                ->withProperties($properties);

            if ($causer) {
                $activity->causedBy($causer);
            }

            $activity->log($description);
        } catch (\Throwable $e) {
            Log::error('BookingActivityLogger::logCreated failed: ' . $e->getMessage());
        }
    }

    /**
     * Log status change.
     */
    public static function logStatusChanged(Booking $booking, string $oldStatus, string $newStatus, ?User $causer = null, ?string $reason = null): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        try {
            $causer = self::resolveCauser($causer);
            $causerName = $causer ? ($causer->full_name ?? $causer->name ?? $causer->first_name) : 'النظام';

            $oldLabel = self::getStatusLabel($oldStatus);
            $newLabel = self::getStatusLabel($newStatus);

            $properties = [
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'old_status_label' => $oldLabel,
                'new_status' => $newStatus,
                'new_status_label' => $newLabel,
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $description = "تم تغيير حالة الحجز من [{$oldLabel}] إلى [{$newLabel}] بواسطة {$causerName}";

            $activity = activity('booking')
                ->performedOn($booking)
                ->event('status_changed')
                ->withProperties($properties);

            if ($causer) {
                $activity->causedBy($causer);
            }

            $activity->log($description);
        } catch (\Throwable $e) {
            Log::error('BookingActivityLogger::logStatusChanged failed: ' . $e->getMessage());
        }
    }

    /**
     * Log booking updates / edits with granular diff.
     */
    public static function logUpdated(Booking $booking, array $oldSnapshot, array $newSnapshot, ?User $causer = null, ?string $customNote = null): void
    {
        try {
            $causer = self::resolveCauser($causer);
            $causerName = $causer ? ($causer->full_name ?? $causer->name ?? $causer->first_name) : 'المستخدم';

            $changes = [];

            // 1. Date & Time
            $oldStart = $oldSnapshot['start_date_time'] ?? null;
            $newStart = $newSnapshot['start_date_time'] ?? $booking->start_date_time;
            if ($oldStart && $newStart && (string) $oldStart !== (string) $newStart) {
                $changes[] = [
                    'key' => 'start_date_time',
                    'label' => 'موعد وتاريخ الحجز',
                    'old' => Carbon::parse($oldStart)->format('Y-m-d h:i A'),
                    'new' => Carbon::parse($newStart)->format('Y-m-d h:i A'),
                ];
            }

            // 2. Branch
            $oldBranchId = $oldSnapshot['branch_id'] ?? null;
            $newBranchId = $newSnapshot['branch_id'] ?? $booking->branch_id;
            if ($oldBranchId && $newBranchId && (int) $oldBranchId !== (int) $newBranchId) {
                $oldBranchName = $oldSnapshot['branch_name'] ?? ('فرع #' . $oldBranchId);
                $booking->loadMissing('branch');
                $newBranchName = $booking->branch->name ?? ('فرع #' . $newBranchId);
                $changes[] = [
                    'key' => 'branch_id',
                    'label' => 'الفرع',
                    'old' => $oldBranchName,
                    'new' => $newBranchName,
                ];
            }

            // 3. Status
            $oldStatus = $oldSnapshot['status'] ?? null;
            $newStatus = $newSnapshot['status'] ?? $booking->status;
            if ($oldStatus && $newStatus && $oldStatus !== $newStatus) {
                $changes[] = [
                    'key' => 'status',
                    'label' => 'حالة الحجز',
                    'old' => self::getStatusLabel($oldStatus),
                    'new' => self::getStatusLabel($newStatus),
                ];
            }

            // 4. Note
            $oldNote = trim($oldSnapshot['note'] ?? '');
            $newNote = trim($newSnapshot['note'] ?? $booking->note ?? '');
            if ($oldNote !== $newNote) {
                $changes[] = [
                    'key' => 'note',
                    'label' => 'الملاحظات',
                    'old' => $oldNote ?: '(فارغ)',
                    'new' => $newNote ?: '(فارغ)',
                ];
            }

            // 5. Staff / Employees
            $oldStaff = $oldSnapshot['staff_names'] ?? [];
            $newStaff = $newSnapshot['staff_names'] ?? [];
            if (!empty($oldStaff) && !empty($newStaff) && $oldStaff !== $newStaff) {
                $changes[] = [
                    'key' => 'employee',
                    'label' => 'الموظف المسؤول',
                    'old' => implode(', ', (array) $oldStaff),
                    'new' => implode(', ', (array) $newStaff),
                ];
            }

            // 6. Services list comparison
            $oldServices = $oldSnapshot['service_names'] ?? [];
            $newServices = $newSnapshot['service_names'] ?? [];
            if (!empty($oldServices) && !empty($newServices) && $oldServices !== $newServices) {
                $changes[] = [
                    'key' => 'services',
                    'label' => 'قائمة الخدمات',
                    'old' => implode(' + ', (array) $oldServices),
                    'new' => implode(' + ', (array) $newServices),
                ];
            }

            // 7. Payment status
            $oldPaymentStatus = isset($oldSnapshot['payment_status']) ? (int) $oldSnapshot['payment_status'] : null;
            $newPaymentStatus = isset($newSnapshot['payment_status']) ? (int) $newSnapshot['payment_status'] : (int) $booking->payment_status;
            if ($oldPaymentStatus !== null && $oldPaymentStatus !== $newPaymentStatus) {
                $changes[] = [
                    'key' => 'payment_status',
                    'label' => 'حالة الدفع',
                    'old' => $oldPaymentStatus == 1 ? 'مدفوع' : 'غير مدفوع',
                    'new' => $newPaymentStatus == 1 ? 'مدفوع' : 'غير مدفوع',
                ];
            }

            // If no specific recognized field changed, record generic update if dirty
            if (empty($changes)) {
                $dirty = $booking->getDirty();
                foreach ($dirty as $field => $newVal) {
                    if (in_array($field, ['updated_at', 'updated_by', 'remember_token'])) {
                        continue;
                    }
                    $changes[] = [
                        'key' => $field,
                        'label' => $field,
                        'old' => $booking->getOriginal($field) ?? '-',
                        'new' => $newVal ?? '-',
                    ];
                }
            }

            if (empty($changes) && empty($customNote)) {
                return; // Nothing changed
            }

            $properties = [
                'action' => 'updated',
                'changes' => $changes,
                'custom_note' => $customNote,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $description = "تم تعديل تفاصيل الحجز بواسطة " . $causerName;

            $activity = activity('booking')
                ->performedOn($booking)
                ->event('updated')
                ->withProperties($properties);

            if ($causer) {
                $activity->causedBy($causer);
            }

            $activity->log($description);
        } catch (\Throwable $e) {
            Log::error('BookingActivityLogger::logUpdated failed: ' . $e->getMessage());
        }
    }

    /**
     * Log booking deletion.
     */
    public static function logDeleted(Booking $booking, ?User $causer = null, ?string $reason = null): void
    {
        try {
            $causer = self::resolveCauser($causer);
            $causerName = $causer ? ($causer->full_name ?? $causer->name ?? $causer->first_name) : 'المستخدم';

            $properties = [
                'action' => 'deleted',
                'deleted_by' => $causerName,
                'reason' => $reason,
                'last_status' => $booking->status,
                'last_status_label' => self::getStatusLabel($booking->status),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $description = "تم حذف الحجز نهائياً بواسطة " . $causerName;

            $activity = activity('booking')
                ->performedOn($booking)
                ->event('deleted')
                ->withProperties($properties);

            if ($causer) {
                $activity->causedBy($causer);
            }

            $activity->log($description);
        } catch (\Throwable $e) {
            Log::error('BookingActivityLogger::logDeleted failed: ' . $e->getMessage());
        }
    }

    /**
     * Log booking restore.
     */
    public static function logRestored(Booking $booking, ?User $causer = null): void
    {
        try {
            $causer = self::resolveCauser($causer);
            $causerName = $causer ? ($causer->full_name ?? $causer->name ?? $causer->first_name) : 'المستخدم';

            $properties = [
                'action' => 'restored',
                'restored_by' => $causerName,
                'status' => $booking->status,
                'status_label' => self::getStatusLabel($booking->status),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $description = "تم استرجاع الحجز بواسطة " . $causerName;

            $activity = activity('booking')
                ->performedOn($booking)
                ->event('restored')
                ->withProperties($properties);

            if ($causer) {
                $activity->causedBy($causer);
            }

            $activity->log($description);
        } catch (\Throwable $e) {
            Log::error('BookingActivityLogger::logRestored failed: ' . $e->getMessage());
        }
    }

    /**
     * Log payment update.
     */
    public static function logPaymentUpdated(Booking $booking, $oldStatus, $newStatus, ?string $transactionType = null, ?User $causer = null): void
    {
        try {
            $causer = self::resolveCauser($causer);
            $causerName = $causer ? ($causer->full_name ?? $causer->name ?? $causer->first_name) : 'المستخدم';

            $oldLabel = $oldStatus == 1 ? 'مدفوع' : 'غير مدفوع';
            $newLabel = $newStatus == 1 ? 'مدفوع' : 'غير مدفوع';

            $properties = [
                'action' => 'payment_updated',
                'old_status' => $oldStatus,
                'old_status_label' => $oldLabel,
                'new_status' => $newStatus,
                'new_status_label' => $newLabel,
                'transaction_type' => $transactionType ?? 'cash',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $description = "تم تحديث حالة الدفع للحجز إلى [{$newLabel}] بواسطة {$causerName}";

            $activity = activity('booking')
                ->performedOn($booking)
                ->event('payment_updated')
                ->withProperties($properties);

            if ($causer) {
                $activity->causedBy($causer);
            }

            $activity->log($description);
        } catch (\Throwable $e) {
            Log::error('BookingActivityLogger::logPaymentUpdated failed: ' . $e->getMessage());
        }
    }

    /**
     * Create snapshot of booking before modifications.
     */
    public static function createSnapshot(Booking $booking): array
    {
        $booking->loadMissing(['services.employee', 'branch']);

        $staffNames = [];
        $serviceNames = [];
        foreach ($booking->services as $s) {
            if ($s->employee) {
                $staffNames[] = $s->employee->full_name ?? $s->employee->name;
            }
            $serviceNames[] = $s->service_name ?? ($s->service ? $s->service->name : 'خدمة');
        }

        return [
            'status' => $booking->status,
            'start_date_time' => $booking->start_date_time,
            'branch_id' => $booking->branch_id,
            'branch_name' => $booking->branch->name ?? null,
            'note' => $booking->note,
            'payment_status' => $booking->payment_status,
            'staff_names' => array_unique($staffNames),
            'service_names' => $serviceNames,
        ];
    }

    /**
     * Retrieve and format all activities for a booking.
     * Also synthesizes baseline creation/deletion for legacy bookings if no Spatie logs exist.
     */
    public static function getFormattedActivities(Booking $booking): array
    {
        $booking->loadMissing(['createdBy', 'updatedBy', 'deletedBy', 'user', 'branch', 'services.employee']);

        $activities = [];

        try {
            $dbActivities = Activity::where('subject_type', Booking::class)
                ->where('subject_id', $booking->id)
                ->with('causer')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            foreach ($dbActivities as $item) {
                $activities[] = self::formatActivityItem($item, $booking);
            }
        } catch (\Throwable $e) {
            Log::warning('BookingActivityLogger could not load from activity_log: ' . $e->getMessage());
        }

        // Check if creation activity exists
        $hasCreation = collect($activities)->contains(function ($item) {
            return ($item['event'] ?? '') === 'created';
        });

        if (!$hasCreation && $booking->created_at) {
            $creator = $booking->createdBy ?? $booking->user;
            $creatorName = $creator ? ($creator->full_name ?? $creator->name ?? 'مستخدم') : 'العميل';

            $servicesNames = $booking->services->pluck('service_name')->filter()->toArray();
            if (empty($servicesNames)) {
                $servicesNames = $booking->services->map(fn($s) => $s->service->name ?? null)->filter()->toArray();
            }

            $activities[] = [
                'id' => 'legacy_create_' . $booking->id,
                'event' => 'created',
                'event_badge' => 'إنشاء الحجز',
                'badge_color' => 'success',
                'icon' => 'fa-solid fa-plus-circle',
                'description' => "تم إنشاء الحجز بواسطة " . $creatorName,
                'causer' => self::formatCauser($creator, 'المنشئ'),
                'created_at' => $booking->created_at->toDateTimeString(),
                'time_ago' => $booking->created_at->diffForHumans(),
                'formatted_date' => $booking->created_at->translatedFormat('d F Y - h:i A'),
                'properties' => [
                    'customer_name' => $booking->user ? ($booking->user->full_name ?? $booking->user->name) : '-',
                    'branch_name' => $booking->branch->name ?? '-',
                    'start_date_time' => $booking->start_date_time ? Carbon::parse($booking->start_date_time)->translatedFormat('d F Y - h:i A') : '-',
                    'status_label' => self::getStatusLabel($booking->status),
                    'services_summary' => implode(', ', $servicesNames),
                ],
                'changes' => [],
            ];
        }

        // Check if deletion activity exists if trashed
        if ($booking->trashed()) {
            $hasDeletion = collect($activities)->contains(function ($item) {
                return ($item['event'] ?? '') === 'deleted';
            });

            if (!$hasDeletion && $booking->deleted_at) {
                $deleter = $booking->deletedBy;
                $deleterName = $deleter ? ($deleter->full_name ?? $deleter->name ?? 'مستخدم') : 'النظام';

                $activities[] = [
                    'id' => 'legacy_delete_' . $booking->id,
                    'event' => 'deleted',
                    'event_badge' => 'حذف الحجز',
                    'badge_color' => 'danger',
                    'icon' => 'fa-solid fa-trash-can',
                    'description' => "تم حذف الحجز نهائياً بواسطة " . $deleterName,
                    'causer' => self::formatCauser($deleter, 'الحاذف'),
                    'created_at' => $booking->deleted_at->toDateTimeString(),
                    'time_ago' => $booking->deleted_at->diffForHumans(),
                    'formatted_date' => $booking->deleted_at->translatedFormat('d F Y - h:i A'),
                    'properties' => [
                        'last_status_label' => self::getStatusLabel($booking->status),
                    ],
                    'changes' => [],
                ];
            }
        }

        // Sort descending by created_at
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return $activities;
    }

    /**
     * Format a single Activity log model into a standardized array.
     */
    protected static function formatActivityItem(Activity $item, Booking $booking): array
    {
        $props = $item->properties ? $item->properties->toArray() : [];
        $event = $item->event ?: ($props['action'] ?? 'updated');

        $badgeColor = 'primary';
        $icon = 'fa-solid fa-clock-rotate-left';
        $eventBadge = 'تحديث';

        switch ($event) {
            case 'created':
                $badgeColor = 'success';
                $icon = 'fa-solid fa-plus-circle';
                $eventBadge = 'إنشاء الحجز';
                break;
            case 'status_changed':
                $badgeColor = 'info';
                $icon = 'fa-solid fa-arrows-rotate';
                $eventBadge = 'تغيير الحالة';
                break;
            case 'updated':
                $badgeColor = 'warning';
                $icon = 'fa-solid fa-pen-to-square';
                $eventBadge = 'تعديل الحجز';
                break;
            case 'deleted':
                $badgeColor = 'danger';
                $icon = 'fa-solid fa-trash-can';
                $eventBadge = 'حذف الحجز';
                break;
            case 'restored':
                $badgeColor = 'primary';
                $icon = 'fa-solid fa-trash-arrow-up';
                $eventBadge = 'استرجاع الحجز';
                break;
            case 'payment_updated':
                $badgeColor = 'success';
                $icon = 'fa-solid fa-credit-card';
                $eventBadge = 'تحديث الدفع';
                break;
        }

        $createdAt = $item->created_at ?: Carbon::now();

        // Extract changes
        $changes = [];
        if (!empty($props['changes'])) {
            $changes = $props['changes'];
        } elseif (isset($props['attributes']) && isset($props['old'])) {
            foreach ($props['attributes'] as $k => $newVal) {
                $oldVal = $props['old'][$k] ?? null;
                if ($newVal !== $oldVal && !in_array($k, ['updated_at', 'updated_by'])) {
                    $changes[] = [
                        'key' => $k,
                        'label' => self::translateField($k),
                        'old' => self::formatFieldValue($k, $oldVal),
                        'new' => self::formatFieldValue($k, $newVal),
                    ];
                }
            }
        }

        return [
            'id' => $item->id,
            'event' => $event,
            'event_badge' => $eventBadge,
            'badge_color' => $badgeColor,
            'icon' => $icon,
            'description' => $item->description,
            'causer' => self::formatCauser($item->causer),
            'created_at' => $createdAt->toDateTimeString(),
            'time_ago' => $createdAt->diffForHumans(),
            'formatted_date' => $createdAt->translatedFormat('d F Y - h:i A'),
            'properties' => $props,
            'changes' => $changes,
        ];
    }

    /**
     * Format Causer User object with name, avatar, and translated role badge.
     */
    public static function formatCauser(?User $user, ?string $fallbackRole = null): array
    {
        if (!$user) {
            return [
                'id' => null,
                'name' => 'النظام / العميل',
                'email' => '-',
                'mobile' => '-',
                'avatar' => default_user_avatar(),
                'role' => $fallbackRole ?? 'النظام',
                'role_badge_class' => 'badge bg-soft-secondary text-secondary',
            ];
        }

        $roleName = $user->roles->first()?->name ?? $user->user_type ?? 'user';
        $roleLabel = match ($roleName) {
            'admin' => 'مدير النظام',
            'manager' => 'مشرف',
            'employee' => 'موظف',
            'user' => 'عميل',
            default => $fallbackRole ?? ucfirst($roleName),
        };

        $roleBadgeClass = match ($roleName) {
            'admin' => 'badge bg-soft-danger text-danger',
            'manager' => 'badge bg-soft-warning text-warning',
            'employee' => 'badge bg-soft-info text-info',
            default => 'badge bg-soft-primary text-primary',
        };

        return [
            'id' => $user->id,
            'name' => $user->full_name ?? ($user->first_name . ' ' . $user->last_name),
            'email' => $user->email ?? '-',
            'mobile' => $user->mobile ?? '-',
            'avatar' => $user->profile_image ?? default_user_avatar(),
            'role' => $roleLabel,
            'role_badge_class' => $roleBadgeClass,
        ];
    }

    /**
     * Translate database column key to Arabic.
     */
    protected static function translateField(string $key): string
    {
        return match ($key) {
            'status' => 'حالة الحجز',
            'start_date_time' => 'موعد وتاريخ الحجز',
            'branch_id' => 'الفرع',
            'user_id' => 'العميل',
            'note' => 'الملاحظات',
            'payment_status' => 'حالة الدفع',
            'employee_id' => 'الموظف المسؤول',
            'services' => 'الخدمات',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * Format field value for display in diff.
     */
    protected static function formatFieldValue(string $key, $value): string
    {
        if ($value === null || $value === '') {
            return '(فارغ)';
        }
        if ($key === 'status') {
            return self::getStatusLabel((string) $value);
        }
        if ($key === 'payment_status') {
            return $value == 1 ? 'مدفوع' : 'غير مدفوع';
        }
        if ($key === 'start_date_time') {
            try {
                return Carbon::parse($value)->translatedFormat('d F Y - h:i A');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }
        return (string) $value;
    }
}
