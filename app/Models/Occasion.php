<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Occasion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'occasion_date',
        'target_type',
        'user_id',
        'message_template',
        'status',
        'sent_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by',
    ];

    protected $casts = [
        'occasion_date' => 'date',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    /**
     * Target user (if target_type == 'specific')
     */
    public function targetUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The admin/user who created this occasion
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * SMS delivery logs
     */
    public function logs()
    {
        return $this->hasMany(OccasionSmsLog::class, 'occasion_id')->latest('id');
    }

    /**
     * Format message template by replacing variables for a given user.
     * Supports both {var} and [[var]] syntaxes.
     */
    public function replaceVariablesForUser(?User $user = null, ?string $customDate = null): string
    {
        $message = $this->message_template;

        $firstName = $user ? trim((string) $user->first_name) : '';
        $lastName = $user ? trim((string) $user->last_name) : '';
        $fullName = $user ? trim((string) $user->full_name) : '';

        if ($firstName === '' && $fullName !== '') {
            $firstName = explode(' ', $fullName)[0] ?? $fullName;
        }

        $occasionDate = $this->occasion_date
            ? $this->occasion_date->format('Y-m-d')
            : ($customDate ?: Carbon::now()->format('Y-m-d'));

        try {
            $appName = setting('app_name') ?: config('app.name', 'JO SPA');
        } catch (\Throwable $e) {
            $appName = config('app.name', 'JO SPA');
        }

        $variables = [
            'name' => $firstName !== '' ? $firstName : 'عميلنا العزيز',
            'full_name' => $fullName !== '' ? $fullName : 'عميلنا العزيز',
            'occasion_name' => $this->name,
            'date' => $occasionDate,
            'phone' => $user ? (string) $user->mobile : '',
            'app_name' => $appName,
        ];

        foreach ($variables as $key => $val) {
            $message = str_replace(["{{$key}}", "[[{$key}]]"], $val, $message);
        }

        return $message;
    }

    /**
     * Generate preview message with mock customer data.
     */
    public static function previewMessage(string $template, ?string $occasionName = null, ?string $date = null): string
    {
        try {
            $appName = setting('app_name') ?: config('app.name', 'JO SPA');
        } catch (\Throwable $e) {
            $appName = config('app.name', 'JO SPA');
        }
        $sampleDate = $date ?: Carbon::now()->format('Y-m-d');
        $sampleOccasion = $occasionName ?: 'المناسبة السعيدة';

        $variables = [
            'name' => 'سارة',
            'full_name' => 'سارة أحمد',
            'occasion_name' => $sampleOccasion,
            'date' => $sampleDate,
            'phone' => '0501234567',
            'app_name' => $appName,
        ];

        $result = $template;
        foreach ($variables as $key => $val) {
            $result = str_replace(["{{$key}}", "[[{$key}]]"], $val, $result);
        }

        return $result;
    }
}
