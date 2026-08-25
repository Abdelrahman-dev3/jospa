<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserAccountNotification extends Notification
{
    use Queueable;

    protected string $notificationType;
    protected string $title;
    protected string $message;
    protected string $url;
    protected string $icon;

    /**
     * Create a new notification instance.
     *
     * @param string $notificationType  e.g. 'booking_paid', 'wallet_topup', etc.
     * @param string $title             Notification title
     * @param string $message           Notification body
     * @param string $url               URL to redirect when clicked
     * @param string $icon              Icon identifier (e.g. 'booking', 'wallet', 'gift', 'loyalty', 'package', 'coupon', 'welcome')
     */
    public function __construct(string $notificationType, string $title, string $message, string $url = '/', string $icon = 'default')
    {
        $this->notificationType = $notificationType;
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
        $this->icon = $icon;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification (stored in `data` column).
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => $this->notificationType,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
