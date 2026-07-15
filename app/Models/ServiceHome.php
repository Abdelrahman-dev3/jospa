<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceHome extends Model
{
    use HasFactory;


    protected $guarded = [];

    public function group()
    {
        return $this->belongsTo(ServiceGroupHome::class, 'service_group_homes_id');
    }

    public function staff()
    {
        return $this->belongsToMany(StaffHome::class, 'staff_service_homes', 'service_home_id', 'staff_home_id');
    }

    public function bookings()
    {
        return $this->hasMany(BookingHome::class, 'service_home_id');
    }



}
