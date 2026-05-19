<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JavnaWebhookEvent extends Model
{
    protected $fillable = [
        'event_scope',
        'event',
        'account_id',
        'message_id',
        'from_number',
        'to_number',
        'status',
        'payload',
        'headers',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'processed_at' => 'datetime',
    ];
}
