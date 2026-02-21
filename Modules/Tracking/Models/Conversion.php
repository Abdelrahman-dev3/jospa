<?php

namespace Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Affiliate\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Tracking\Database\factories\ConversionFactory;

class Conversion extends Model
{
    use HasFactory;
    protected $table = 'affiliate_conversions';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'affiliate_id',
        'visitor_id',
        'order_id',
        'amount',
        'commission',
        'status',
    ];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }
}
