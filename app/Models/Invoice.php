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
        'coupon_discount_amount',
        'payment_gateway_discount_amount',
        'payment_gateway_discount_method',
        'payment_gateway_discount_label',
        'taxs_service',
        'loyalty_points_discount',
        'final_total',
        'javna_whatsapp_message_id',
        'javna_whatsapp_message_id',
        'javna_whatsapp_status',
        'javna_whatsapp_payload_style',
        'javna_whatsapp_sent_at',
        'javna_whatsapp_last_event_at',
    ];

    protected $casts = [
        'cart_ids' => 'array',
        'gift_ids' => 'array',
        'product_ids' => 'array',
        'javna_whatsapp_sent_at' => 'datetime',
        'javna_whatsapp_last_event_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    

    public function getNormalizedCartIdsAttribute(): array
    {
        $raw = $this->cart_ids;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $raw)));
    }

    public function getNormalizedGiftIdsAttribute(): array
    {
        $raw = $this->gift_ids;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $raw)));
    }

    public function getNormalizedProductIdsAttribute(): array
    {
        $raw = $this->product_ids;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $raw)));
    }

    public function getBookingsAttribute()
    {
        $ids = $this->normalized_cart_ids;
        if (empty($ids)) {
            return collect();
        }
        return \Modules\Booking\Models\Booking::whereIn('id', $ids)
            ->with(['services.employee', 'branch', 'user'])
            ->get();
    }

    public function getGiftCardsAttribute()
    {
        $ids = $this->normalized_gift_ids;
        if (empty($ids)) {
            return collect();
        }
        return \App\Models\GiftCard::whereIn('id', $ids)->get();
    }

    public function getProductsAttribute()
    {
        $ids = $this->normalized_product_ids;
        if (empty($ids)) {
            return collect();
        }
    
        return OrderGroup::with([
            'order.orderItems.product'
        ])
        ->whereIn('id', $ids)
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
        $ids = $this->normalized_product_ids;
        if (empty($ids)) {
            return collect();
        }

        return OrderGroup::with([
            'order.orderItems.product'
        ])
        ->whereIn('id', $ids)
        ->get()
        ->flatMap(function ($group) {
            return optional($group->order)->orderItems ?? [];
        });
    }

}
