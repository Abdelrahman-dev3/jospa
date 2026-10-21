<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Service\Models\Service;
use Modules\Package\Models\Package;

class GiftCard extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = [
        'ref',
        'balance',
        'pdf_url',
        'delivery_method',
        'sender_name',
        'recipient_name',
        'sender_phone',
        'recipient_phone',
        'message',
        'requested_services',
        'package_ids',
        'user_id',
        'options_amount',
        'subtotal',
        'coupons',
        'payment_status',
        'show_sender_name',
        'show_recipient_name',
        'show_sender_phone',
        'show_recipient_phone',
        'branch_id',
        'bouquet_id',
        'bouquet_name',
        'bouquet_price',
        'card_amount',
    ];

    protected $casts = [
        'requested_services' => 'array',
        'package_ids' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /** True for gifts bought from a branch's dedicated gifting page. */
    public function isBranchGift(): bool
    {
        return ! empty($this->branch_id);
    }

    /**
     * Spendable balance issued when the gift is paid. For branch gifts the
     * bouquet is a physical product handed over manually, so only the card
     * amount becomes balance.
     */
    public function creditAmount(): float
    {
        if ($this->isBranchGift()) {
            return (float) ($this->card_amount ?? 0);
        }

        return (float) ($this->subtotal ?? $this->options_amount ?? 0);
    }

    /** Whether paying for this gift should issue a redeemable reference code. */
    public function requiresReference(): bool
    {
        if ($this->isBranchGift()) {
            return $this->creditAmount() > 0;
        }

        return in_array($this->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true);
    }

    public static function generateUniqueReference(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = sprintf(
                '%04d-%04d-%d%s%d%s',
                random_int(0, 9999),
                random_int(0, 9999),
                random_int(0, 9),
                chr(random_int(97, 122)),
                random_int(0, 9),
                chr(random_int(97, 122))
            );

            if (! static::where('ref', $reference)->exists()) {
                return $reference;
            }
        }

        throw new \RuntimeException('Unable to generate a unique gift card reference.');
    }

public function getCouponsAttribute($value)
{
    return json_decode($value, true) ?? [];
}
    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

    public function getServicesListAttribute()
    {
        $serviceIds = json_decode($this->requested_services ?? '[]', true);
    
        if (!is_array($serviceIds)) {
            return collect();
        }
    
        return Service::whereIn('id', $serviceIds)->get();
    }
    
        public function getPackagesAttribute()
    {
        $ids = $this->package_ids;

        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? [];
        }

        if (empty($ids) || !is_array($ids)) {
            return collect();
        }

        return Package::whereIn('id', $ids)->get();
    }

}
