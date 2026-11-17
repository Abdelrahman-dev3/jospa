<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One HyperPay checkout opened for a payment request. */
class PaymentRequestAttempt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const FINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    protected $table = 'payment_request_attempts';

    protected $fillable = [
        'uuid',
        'payment_request_id',
        'amount',
        'currency',
        'brand',
        'status',
        'merchant_transaction_id',
        'checkout_id',
        'gateway_transaction_id',
        'result_code',
        'result_description',
        'gateway_response',
        'expires_at',
        'paid_at',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = ['gateway_response'];

    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /** Pending with a live gateway session the customer can still pay on. */
    public function isResumable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ! empty($this->checkout_id)
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
