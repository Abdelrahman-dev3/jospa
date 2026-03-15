<?php

namespace Modules\Promotion\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Promotion\Database\factories\CouponFactory;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 'promotions_coupon';

    protected $fillable = ['coupon_code', 'coupon_type', 'is_expired', 'timezone', 'services', 'discount_type', 'discount_percentage', 'discount_amount', 'start_date_time', 'end_date_time', 'promotion_id', 'use_limit'];

    protected $casts = [
        'services' => 'array',
    ];

    protected static function newFactory(): CouponFactory
    {
        // return CouponFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($coupon) {
            $coupon->syncExpirationStatus();
        });
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function userCouponRedeem()
    {
        return $this->hasMany(UserCouponRedeem::class, 'coupon_id');
    }

    public function userRedeems()
    {
        return $this->hasManyThrough(User::class, UserCouponRedeem::class, 'coupon_id', 'id', 'id', 'user_id');
    }

    public function hasRemainingUses(): bool
    {
        return $this->remainingUses() > 0;
    }

    public function remainingUses(): int
    {
        $limit = (int) ($this->use_limit ?? 0);
        $used = $this->userCouponRedeem()->count();

        return max($limit - $used, 0);
    }

    public function isWithinActiveDateRange(?Carbon $date = null): bool
    {
        $date = $date ?? now();

        if (! $this->start_date_time || ! $this->end_date_time) {
            return false;
        }

        $startsAt = Carbon::parse($this->start_date_time)->startOfDay();
        $endsAt = Carbon::parse($this->end_date_time)->endOfDay();

        return $date->between($startsAt, $endsAt);
    }

    public function syncExpirationStatus(): void
    {
        $shouldBeExpired = ! $this->isWithinActiveDateRange() || ! $this->hasRemainingUses();

        if ((bool) $this->is_expired === $shouldBeExpired) {
            return;
        }

        $this->forceFill(['is_expired' => $shouldBeExpired])->saveQuietly();
    }
}
