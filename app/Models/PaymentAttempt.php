<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'gateway',
        'payment_method',
        'page',
        'status',
        'currency',
        'amount',
        'discount_amount',
        'cart_ids',
        'gift_ids',
        'coupon_code',
        'gift_code',
        'wallet_used',
        'loyalty_used',
        'merchant_reference',
        'gateway_transaction_id',
        'gateway_checkout_id',
        'gateway_order_id',
        'gateway_response',
        'callback_payload',
        'error_message',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'discount_amount' => 'float',
        'cart_ids' => 'array',
        'gift_ids' => 'array',
        'wallet_used' => 'boolean',
        'loyalty_used' => 'boolean',
        'gateway_response' => 'array',
        'callback_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getPaymentBrandLabelAttribute(): string
    {
        $brandPaths = [
            'paymentBrand',
            'payment_brand',
            'payment.brand',
            'card.brand',
            'brand',
            'hyperpay_brand',
            'payment_context.hyperpay_brand',
        ];

        $brand = null;
        foreach ([$this->gateway_response, $this->callback_payload] as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach ($brandPaths as $path) {
                $value = data_get($payload, $path);
                if (is_string($value) && trim($value) !== '') {
                    $brand = strtoupper(trim($value));
                    break 2;
                }
            }
        }

        if ($brand !== null) {
            return match ($brand) {
                'VISA' => 'Visa',
                'MASTER', 'MASTERCARD' => 'Mastercard',
                'MADA' => 'Mada',
                'STC', 'STCPAY', 'STC_PAY', 'STC PAY' => 'STC Pay',
                default => ucwords(strtolower(str_replace(['_', '-'], ' ', $brand))),
            };
        }

        return match (strtolower((string) ($this->payment_method ?: $this->gateway))) {
            'tabby' => 'Tabby',
            'tamara' => 'Tamara',
            'urpay' => 'UrPay',
            'stcpay' => 'STC Pay',
            'card' => 'Visa / Mastercard',
            default => strtoupper((string) ($this->payment_method ?: $this->gateway ?: '-')),
        };
    }
}
