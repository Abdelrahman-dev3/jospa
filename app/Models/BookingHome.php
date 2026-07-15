<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingHome extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function staffHome()
    {
        return $this->belongsTo(StaffHome::class, 'staff_home_id');
    }

    public function serviceHome()
    {
        return $this->belongsTo(ServiceHome::class, 'service_home_id');
    }
}
