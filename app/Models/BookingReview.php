<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;

class BookingReview extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    /** How long a review request stays answerable (WhatsApp conversation or web link). */
    public const WINDOW_DAYS = 7;

    protected $fillable = [
        'booking_id', 'user_id', 'branch_id', 'employee_id', 'service_id', 'token', 'status', 'source',
        'current_step', 'overall_rating', 'specialist_rating', 'service_rating', 'last_inbound_message_id',
        'request_sent_at', 'request_message_id', 'request_attempts', 'completed_at',
        'points_awarded', 'points_awarded_at', 'confirmation_sent_at',
    ];

    protected $casts = [
        'request_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'points_awarded_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isAnswerable(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $anchor = $this->request_sent_at ?? $this->created_at;

        return $anchor && $anchor->gt(now()->subDays(self::WINDOW_DAYS));
    }

    /** Average of the three answers on a 1-4 scale. */
    public function getAverageRatingAttribute(): ?float
    {
        $values = array_filter([$this->overall_rating, $this->specialist_rating, $this->service_rating]);

        return $values ? round(array_sum($values) / count($values), 2) : null;
    }
}
