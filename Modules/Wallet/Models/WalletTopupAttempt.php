<?php

namespace Modules\Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WalletTopupAttempt extends Model
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

    protected $table = 'wallet_topup_attempts';

    protected $fillable = [
        'attempt_id',
        'user_id',
        'idempotency_key',
        'amount',
        'currency',
        'gateway',
        'brand',
        'status',
        'merchant_transaction_id',
        'checkout_id',
        'gateway_transaction_id',
        'result_code',
        'result_description',
        'wallet_history_id',
        'gateway_response',
        'expires_at',
        'paid_at',
        'last_checked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'gateway_response',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /** Pending, has a gateway checkout and its session has not expired: the app may reopen payment_url. */
    public function isResumable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ! empty($this->checkout_id)
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
