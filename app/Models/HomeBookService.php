<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeBookService extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'mobile_no',
        'neighborhood',
        'gender',
        'service_group_id',
        'service_id',
        'date',
        'time',
        'staff_id',
        'branch',
    ];



}
