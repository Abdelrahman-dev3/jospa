<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ouroffersection extends Model
{
    protected $fillable = [
        'discount_type',
        'discount_value',
        'description',
        'start_date',
        'end_date',
        'color',
        'image',
        'link',
        'overlay',
    ];

    protected $casts = [
        'description' => 'array',
    ];
}
