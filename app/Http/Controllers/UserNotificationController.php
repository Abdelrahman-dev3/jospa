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
            ->where('type', 'App\\Notifications\\UserAccountNotification')
            ->paginate($perPage);

        $items = $notifications->map(function ($notification) {
            $data = $notification->data;
            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? 'default',
                'title' => $data['title'] ?? '',
                'message' => $data['message'] ?? '',
                'url' => $data['url'] ?? '/',
                'icon' => $data['icon'] ?? 'default',
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $this->timeAgo($notification->created_at),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $items,
            'unread_count' => $user->unreadNotifications()
                ->where('type', 'App\\Notifications\\UserAccountNotification')
                ->count(),
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
        $count = auth()->user()->unreadNotifications()
            ->where('type', 'App\\Notifications\\UserAccountNotification')
            ->count();

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
        auth()->user()->unreadNotifications()
            ->where('type', 'App\\Notifications\\UserAccountNotification')
            ->update(['read_at' => now()]);

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
