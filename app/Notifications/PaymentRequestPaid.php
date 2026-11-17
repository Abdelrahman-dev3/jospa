<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to dashboard admins once HyperPay has confirmed a payment request was paid.
 * Payload shape matches the existing bell dropdown: data.subject + data.data.notification_group.
 */
class PaymentRequestPaid extends Notification
{
    use Queueable;

    public const EVENT_PAID = 'paid';

    /** Paid after an admin cancelled the request (checkout was already open). */
    public const EVENT_PAID_AFTER_CANCEL = 'paid_after_cancel';

    /** A second payment for an already-paid request: needs a manual refund. */
    public const EVENT_DUPLICATE = 'duplicate';

    public function __construct(
        public PaymentRequest $paymentRequest,
        public string $event,
        private array $channels = ['database'],
    ) {}

    public function via($notifiable): array
    {
        return $this->channels;
    }

    public function subject(): string
    {
        return __('payment_requests.notification.subject_'.$this->event, ['number' => $this->paymentRequest->number]);
    }

    public function toArray($notifiable): array
    {
        $pr = $this->paymentRequest;

        return [
            'subject' => $this->subject(),
            'message' => $this->line(),
            'data' => [
                'notification_group' => 'payment_request',
                'notification_type' => 'payment_request_'.$this->event,
                'id' => $pr->id,
                'number' => $pr->number,
                'user_name' => $pr->customer_name,
                'service_name' => Str::limit((string) $pr->description, 80),
                'amount' => number_format((float) $pr->amount, 2, '.', ''),
                'currency' => $pr->currency,
                'paid_at' => optional($pr->paid_at)->toDateTimeString(),
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $pr = $this->paymentRequest;

        return (new MailMessage)
            ->subject($this->subject())
            ->line($this->line())
            ->line(__('payment_requests.fields.description').': '.Str::limit((string) $pr->description, 200))
            ->line(__('payment_requests.fields.reference').': '.($pr->gateway_transaction_id ?: '-'))
            ->action(__('payment_requests.title'), route('backend.payment-requests.index', ['search' => $pr->number]));
    }

    private function line(): string
    {
        $pr = $this->paymentRequest;

        return __('payment_requests.notification.line_'.$this->event, [
            'number' => $pr->number,
            'name' => $pr->customer_name,
            'amount' => number_format((float) $pr->amount, 2),
        ]);
    }
}
