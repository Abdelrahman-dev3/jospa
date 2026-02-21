<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCart extends Model
{
    use HasFactory;
        protected $fillable = [
        'n_name',
        'customer_id',
        'mobile_no',
        'neighborhood',
        'branch',
        'gender',
        'service_group_id',
        'service_id',
        'date',
        'time',
        'staff_id',
        'status',
        'agreed',
        'auto_change_staff',
    ];


    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function serviceGroup()
    {
        return $this->belongsTo(ServiceGroupHome::class, 'service_group_id');
    }

    public function service()
    {
        return $this->belongsTo(ServiceHome::class, 'service_id');
    }

    public function staff()
    {
        return $this->belongsTo(StaffHome::class, 'staff_id');
    }
}
