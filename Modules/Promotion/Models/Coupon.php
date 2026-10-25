<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Promotion\Database\factories\CouponFactory;
use App\Models\User;

class Coupon extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'promotions_coupon';

    protected $fillable = ['coupon_code','coupon_type','is_expired', 'timezone', 'services' , 'specific_dates', 'branch_ids', 'discount_type', 'discount_percentage', 'discount_amount', 'start_date_time', 'end_date_time', 'promotion_id', 'use_limit'];

    protected $casts = [
        'services' => 'array',
        'specific_dates' => 'array',
        'branch_ids' => 'array',
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
    ];

    protected static function newFactory(): CouponFactory
    {
        //return CouponFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($coupon) {
            $coupon->syncExpiredState();
        });
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query) {
                $query->whereNull('start_date_time')
                    ->orWhere('start_date_time', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('end_date_time')
                    ->orWhere('end_date_time', '>=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('use_limit')
                    ->orWhere('use_limit', '<=', 0)
                    ->orWhereRaw(
                        '(select count(*) from user_coupon_redeem where user_coupon_redeem.coupon_id = promotions_coupon.id) < promotions_coupon.use_limit'
                    );
            })
            ->where(function (Builder $query) {
                // If specific_dates is set, only allow usage on those dates
                $today = now()->toDateString();
                $query->whereNull('specific_dates')
                    ->orWhereJsonLength('specific_dates', 0)
                    ->orWhereJsonContains('specific_dates', $today);
            });
    }

    /**
     * Check if the coupon is available today based on specific_dates.
     * If no specific dates are set, the coupon is always available (date-wise).
     */
    public function isAvailableToday(): bool
    {
        $specificDates = $this->specific_dates;

        if (empty($specificDates) || ! is_array($specificDates)) {
            return true;
        }

        return in_array(now()->toDateString(), $specificDates, true);
    }

    /** Branch ids this coupon is limited to; empty means every branch. */
    public function restrictedBranchIds(): array
    {
        $ids = $this->branch_ids;

        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    public function isRestrictedToBranches(): bool
    {
        return $this->restrictedBranchIds() !== [];
    }

    /**
     * Whether the coupon can be used for a booking in the given branch.
     * A branch-restricted coupon is not valid for bookings without a branch (e.g. home service).
     */
    public function appliesToBranch(?int $branchId): bool
    {
        if (! $this->isRestrictedToBranches()) {
            return true;
        }

        return $branchId !== null && in_array((int) $branchId, $this->restrictedBranchIds(), true);
    }

    /** Valid only if every given booking branch is allowed (used for invoice-wide coupons). */
    public function appliesToAllBranches(iterable $branchIds): bool
    {
        if (! $this->isRestrictedToBranches()) {
            return true;
        }

        $count = 0;
        foreach ($branchIds as $branchId) {
            $count++;
            if (! $this->appliesToBranch($branchId === null ? null : (int) $branchId)) {
                return false;
            }
        }

        return $count > 0;
    }

    public function usageCount(): int
    {
        return (int) ($this->relationLoaded('redemptions')
            ? $this->redemptions->count()
            : $this->redemptions()->count());
    }

    public function hasReachedUseLimit(): bool
    {
        $useLimit = (int) ($this->use_limit ?? 0);

        return $useLimit > 0 && $this->usageCount() >= $useLimit;
    }

    public function hasEnded(): bool
    {
        return ! is_null($this->end_date_time) && $this->end_date_time->lt(now());
    }

    public function isCurrentlyExpired(): bool
    {
        $isExpired = $this->hasEnded() || $this->hasReachedUseLimit();

        if ((int) $this->is_expired !== (int) $isExpired) {
            $this->forceFill([
                'is_expired' => $isExpired ? 1 : 0,
            ])->saveQuietly();
        } else {
            $this->is_expired = $isExpired ? 1 : 0;
        }

        return $isExpired;
    }

    public function syncExpiredState(): void
    {
        $this->isCurrentlyExpired();
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }
    public function userCouponRedeem(){
        return $this->hasMany(UserCouponRedeem::class, 'coupon_code');
    }

    public function redemptions()
    {
        return $this->hasMany(UserCouponRedeem::class, 'coupon_id');
    }

    public function userRedeems()
    {
        return $this->hasManyThrough(User::class, UserCouponRedeem::class, 'coupon_id', 'id', 'id', 'user_id');
    }
}
