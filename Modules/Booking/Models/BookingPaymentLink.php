<?php

namespace Modules\Booking\Models;

use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Model;

class BookingPaymentLink extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'booking_id',
        'token',
        'amount',
        'currency',
        'status',
        'payment_attempt_id',
        'invoice_id',
        'created_by',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function attempt()
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }

    public function publicUrl(): string
    {
        return route('booking-payment-link.show', ['token' => $this->token]);
    }
}
