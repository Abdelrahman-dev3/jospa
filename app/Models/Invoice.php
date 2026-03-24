<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Product\Models\OrderGroup;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cart_ids',
        'gift_ids',
        'product_ids',
        'coupon_code',
        'gift_code',
        'gift_amount',
        'payment_method',
        'discount_amount',
        'taxs_service',
        'loyalty_points_discount',
        'final_total',
    ];

    protected $casts = [
        'cart_ids' => 'array',
        'gift_ids' => 'array',
        'product_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    

    public function getProductsAttribute()
    {
        if (empty($this->product_ids)) {
            return collect();
        }
    
        return OrderGroup::with([
            'order.orderItems.product'
        ])
        ->whereIn('id', $this->product_ids)
        ->get()
        ->flatMap(function ($group) {
            return optional($group->order)->orderItems ?? [];
        })
        ->map(function ($item) {
            return $item->product;
        })
        ->filter();
    }

    public function getProductItemsAttribute()
    {
        if (empty($this->product_ids)) {
            return collect();
        }

        return OrderGroup::with([
            'order.orderItems.product'
        ])
        ->whereIn('id', $this->product_ids)
        ->get()
        ->flatMap(function ($group) {
            return optional($group->order)->orderItems ?? [];
        });
    }

}

