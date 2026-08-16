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
    ];

    protected $casts = [
        'requested_services' => 'array',
        'package_ids' => 'array',
    ];

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
