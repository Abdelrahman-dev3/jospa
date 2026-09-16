<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OccasionSmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'occasion_id',
        'user_id',
        'customer_name',
        'phone',
        'message',
        'status',
        'response_data',
    ];

    public function occasion()
    {
        return $this->belongsTo(Occasion::class, 'occasion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
