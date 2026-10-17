<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffCustomWorkingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'date',
        'start_time',
        'end_time',
        'is_holiday',
        'breaks',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'is_holiday' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
