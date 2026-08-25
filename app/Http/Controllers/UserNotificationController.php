<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserNotificationController extends Controller
{
    /**
     * Get user notifications (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $perPage = (int) $request->get('per_page', 10);

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = $notifications->getCollection()->map(function ($notification) {
            $raw = $notification->data;
            if (is_string($raw)) {
                $raw = json_decode($raw, true) ?? [];
            }
            if (!is_array($raw)) {
                $raw = [];
            }

            $inner = (isset($raw['data']) && is_array($raw['data'])) ? $raw['data'] : [];

            $title = (!empty($raw['title']) ? $raw['title'] : null) 
                ?? (!empty($raw['subject']) ? $raw['subject'] : null) 
                ?? (!empty($raw['heading']) ? $raw['heading'] : null) 
                ?? (!empty($inner['subject']) ? $inner['subject'] : null) 
                ?? (!empty($inner['title']) ? $inner['title'] : null) 
                ?? (!empty($inner['type']) ? $inner['type'] : null) 
                ?? __('notifications.notifications'); 

            $message = (!empty($raw['message']) ? $raw['message'] : null)
                ?? (!empty($raw['text']) ? $raw['text'] : null)
                ?? (!empty($raw['body']) ? $raw['body'] : null)
                ?? (!empty($raw['description']) ? $raw['description'] : null)
                ?? (!empty($inner['message']) ? $inner['message'] : null)
                ?? (!empty($inner['text']) ? $inner['text'] : null)
                ?? (!empty($inner['body']) ? $inner['body'] : null)
                ?? (!empty($inner['notification_message']) ? $inner['notification_message'] : null)
                ?? (!empty($raw['notification_message']) ? $raw['notification_message'] : null)
                ?? '';

            if (!empty($message)) {
                $message = trim(strip_tags($message));
            }

            $icon = (!empty($raw['icon']) ? $raw['icon'] : null)
                ?? (!empty($inner['icon']) ? $inner['icon'] : null)
                ?? 'default';

            $url = (!empty($raw['url_frontend']) ? $raw['url_frontend'] : null)
                ?? (!empty($raw['url']) ? $raw['url'] : null)
                ?? (!empty($inner['url']) ? $inner['url'] : null)
                ?? (!empty($inner['link']) ? $inner['link'] : null)
                ?? (!empty($raw['link']) ? $raw['link'] : null)
                ?? route('profile.my_bookings');

            $type = $raw['type']
                ?? $inner['type']
                ?? 'default';

            return [
                'id' => $notification->id,
                'type' => (string) $type,
                'title' => (string) $title,
                'message' => (string) $message,
                'url' => (string) $url,
                'icon' => (string) $icon,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $this->timeAgo($notification->created_at),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => array_values($items->toArray()),
            'unread_count' => $user->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(): JsonResponse
    {
        $count = auth()->user()->unreadNotifications()->count();

        return response()->json([
            'status' => true,
            'count' => $count,
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => __('notifications.mark_all_read'),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'status' => true,
        ]);
    }

    /**
     * Get notifications page view.
     */
    public function page()
    {
        return view('frontend.account.notifications');
    }

    /**
     * Generate human-readable time ago string.
     */
    private function timeAgo($date): string
    {
        $now = now();
        $diff = $now->diff($date);

        if ($diff->days === 0 && $diff->h === 0 && $diff->i === 0) {
            return __('notifications.just_now');
        }

        if ($diff->days === 0 && $diff->h === 0) {
            return __('notifications.minutes_ago', ['min' => $diff->i]);
        }

        if ($diff->days === 0) {
            return __('notifications.hours_ago', ['hours' => $diff->h]);
        }

        return __('notifications.days_ago', ['days' => $diff->days]);
    }
}
