<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftBouquet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'name_ar', 'name_en', 'price', 'image', 'badge', 'sort_order', 'status',
    ];

    protected $casts = [
        'price' => 'float',
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image
            ? asset($this->image)
            : asset('images/gifting/bouquet_pink.jpg');
    }

    /** Shape consumed by the public gifting page and the mobile API. */
    public function toGiftArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->name_ar,
            'sub' => $this->name_en,
            'price' => $this->price,
            'image' => $this->image_url,
            'badge' => $this->badge,
        ];
    }
}
