<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $request;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $request)
    {
        $this->request = $request;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $request = $this->request;
        $user = $notifiable;

        return (new MailMessage())
            ->subject(__('users.account_credentials_subject'))
            ->greeting(__('users.account_mail_greeting', ['name' => $user->full_name ?: $user->email]))
            ->line(__('users.account_mail_intro'))
            ->line(__('users.account_mail_email', ['email' => $user->email]))
            ->line(__('users.account_mail_mobile', ['mobile' => $user->mobile]))
            ->action(__('users.account_mail_action'), $request['login_url'] ?? url('/login'))
            ->line(__('users.account_mail_outro'));
    }
}
