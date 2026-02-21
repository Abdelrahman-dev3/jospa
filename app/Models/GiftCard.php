<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Service\Models\Service;
use Modules\Package\Models\Package;

class GiftCard extends Model
{
    protected $fillable = [
        'ref',
        'balance',
        'delivery_method',
        'sender_name',
        'recipient_name',
        'sender_phone',
        'recipient_phone',
        'requested_services',
        'package_ids',
        'user_id',
        'options_amount',
        'subtotal',
        'coupons',
        'payment_status',
    ];

    protected $casts = [
        'requested_services' => 'array',
        'package_ids' => 'array',
    ];
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