<?php

namespace Modules\Booking\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Package\Models\BookingPackages;
use Modules\Package\Models\UserPackageServices;
use Modules\Package\Models\BookingPackageService;
use Modules\Promotion\Models\UserCouponRedeem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Commission\Trait\CommissionTrait;
use Modules\Service\Models\Service;
use Modules\Package\Models\UserPackageRedeem;
use Modules\Package\Models\UserPackage;
use Illuminate\Support\Facades\DB;


class Booking extends BaseModel
{
    use CommissionTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'bookings';

    protected $casts = [
        'user_id' => 'integer',
        'branch_id' => 'integer',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Modules\Booking\database\factories\BookingFactory::new();
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class, 'branch_id')->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function services()
    {
        $locale = app()->getLocale();
        return $this->hasMany(BookingService::class, 'booking_id')->with('employee')
            ->leftJoin('services', 'booking_services.service_id', 'services.id')
            ->selectRaw("
                            JSON_UNQUOTE(JSON_EXTRACT(services.name, '$.\"$locale\"')) as service_name,
                            'services.*',
                            booking_services.*
                        ");    
    }

    public function packages()
    {
        return $this->hasMany(BookingPackages::class, 'booking_id')->with('employee')
            ->leftJoin('packages', 'booking_packages.package_id', 'packages.id')
            ->select('packages.name as name', 'packages.description', 'booking_packages.*');
    }

    public function userPackageReddem()
    {
        return $this->hasMany(userPackageRedeem::class)->with('package');
    }


    public function products()
    {
        return $this->hasMany(BookingProduct::class, 'booking_id')
            ->leftJoin('products', 'booking_products.product_id', 'products.id')
            ->selectRaw('IFNULL(CONCAT(products.name, " - ", booking_products.variation_name), products.name) as product_name, booking_products.*')
            ->with('employee')->with('product.media');
    }

    public function booking_service()
    {
        return $this->hasMany(BookingService::class, 'booking_id', 'id')->with('employee', 'service');

    }

    public function service()
    {
        return $this->hasOne(BookingService::class, 'booking_id', 'id')->with(['employee', 'service']);
    }

    public function mainServices()
    {
        return $this->hasManyThrough(Service::class, BookingService::class, 'booking_id', 'id', 'id', 'service_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookingTransaction()
    {
        return $this->hasOne(BookingTransaction::class)->where('payment_status', 1)->latest('id');
    }

    public function transactions()
    {
        return $this->hasMany(BookingTransaction::class);
    }

    public function payment()
    {
        return $this->hasOne(BookingTransaction::class)->latest('id');
    }

    public function bookingService()
    {
        return $this->hasMany(BookingService::class);
    }


    public function userCouponRedeem()
    {
        return $this->hasOne(UserCouponRedeem::class, 'booking_id');
    }

    public function userPackages()
    {
        return $this->hasMany(UserPackage::class);
    }
    public function bookingPackages()
    {
        $locale = app()->getLocale();
        return $this->hasMany(BookingPackages::class, 'booking_id', 'id')
            ->leftJoin('packages', 'booking_packages.package_id', 'packages.id')
            ->selectRaw("
                JSON_UNQUOTE(JSON_EXTRACT(packages.name, '$.\"$locale\"')) as name,
                packages.description,
                packages.start_date,
                packages.end_date,
                booking_packages.*
            ");
    }

    public function scopeBranch($query)
    {
        $branch_id = request()->selected_session_branch_id;
        if (isset($branch_id)) {
            return $query->where('branch_id', $branch_id);
        } else {
            return $query->whereNotNull('branch_id');
        }
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereHas('transactions', function (Builder $transactionQuery) {
            $transactionQuery->where('payment_status', 1);
        });
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereDoesntHave('transactions', function (Builder $transactionQuery) {
            $transactionQuery->where('payment_status', 1);
        });
    }

    public static function cleanupExpiredBookings()
    {
        try {
            // 10 minutes: bookings that were created but never paid within this window
            // are considered abandoned. This runs ONLY when creating a new booking,
            // never during payment gateway callbacks — see getUserIncompleteBookings().
            $tenMinutesAgo = \Carbon\Carbon::now()->subMinutes(10);

            $expiredBookings = static::where('status', 'pending')
                ->whereIn('payment_type', ['cart', 'payment'])
                ->where('created_at', '<=', $tenMinutesAgo)
                ->unpaid()
                // Safety guard: never delete a booking whose user has an active
                // payment_attempt in the last 10 minutes. Handles edge cases where
                // the explicit call site guard below is bypassed.
                ->whereNotExists(function ($query) {
                    $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('payment_attempts')
                        ->whereColumn('payment_attempts.user_id', 'bookings.user_id')
                        ->whereIn('payment_attempts.status', ['pending', 'processing'])
                        ->where('payment_attempts.created_at', '>=', now()->subHours(2));
                })
                ->get();

            foreach ($expiredBookings as $booking) {
                $booking->bookingService()->delete();
                $booking->packages()->delete();
                $booking->products()->delete();
                $booking->transactions()->delete();
                $booking->userCouponRedeem()->delete();
                $booking->delete();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to auto-delete expired bookings: " . $e->getMessage());
        }
    }

    public static function userBaseQuery(int $userId, array $relations = []): Builder
    {
        return static::with($relations)->where('created_by', $userId)->whereNull('deleted_by');
    }

    public static function getUserIncompleteBookings(int $userId,?string $paymentType = null,array $relations = ['service.service', 'service.employee']): Collection {
        // NOTE: cleanupExpiredBookings() is intentionally NOT called here.
        // This method is used by PaymentCalculatorService during payment gateway
        // callbacks. Running cleanup at that point causes a race condition where
        // a pending booking that is > 10 minutes old gets deleted just before
        // it is confirmed — resulting in payment being captured with no booking saved.
        // Cleanup is triggered only from BookingCartController::store() (new booking creation).

        return static::userBaseQuery($userId, $relations)
            ->when($paymentType, fn (Builder $query) => $query->where('payment_type', $paymentType))
            ->unpaid()
            ->whereNotIn('status', ['completed', 'cancelled', 'canceled'])
            ->get();
    }

    public static function getCompletedBookings(int $userId,?string $paymentType = null,array $relations = ['service.service', 'service.employee']): Collection {
        return static::userBaseQuery($userId, $relations)
            ->when($paymentType, fn (Builder $query) => $query->where('payment_type', $paymentType))
            ->paid()
            ->where('status', 'completed')
            ->get();
    }

    // Reports Query
    public static function dailyReport()
    {
        return self::select(
            DB::raw('DATE(bookings.start_date_time) AS start_date_time'),
            DB::raw('COUNT(DISTINCT bookings.id) AS total_booking'),
            DB::raw('COALESCE(SUM(bs.total_service), 0) AS total_service'),
            DB::raw('COALESCE(SUM(bs.total_service_amount), 0) AS total_service_amount'),
            DB::raw('COALESCE(SUM(tx_sum.total_tax_amount), 0) AS total_tax_amount'),
            DB::raw('(COALESCE(SUM(bs.total_service_amount), 0) + COALESCE(SUM(tx_sum.total_tax_amount), 0)) AS total_amount')
        )
            ->leftJoin(DB::raw('(
                SELECT booking_id,
                       SUM(service_price) as total_service_amount,
                       COUNT(service_id) as total_service
                FROM booking_services
                GROUP BY booking_id
            ) AS bs'), 'bookings.id', '=', 'bs.booking_id')
            ->leftJoin(DB::raw('(
                SELECT bt.booking_id,
                       SUM(
                           CASE
                               WHEN JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].type\'))) = \'percent\'
                                   THEN (SELECT COALESCE(SUM(bs_in.service_price), 0) FROM booking_services bs_in WHERE bs_in.booking_id = bt.booking_id) * CAST(JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].percent\'))) AS DECIMAL(10,2)) / 100
                               WHEN JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].type\'))) = \'fixed\'
                                   THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].tax_amount\'))) AS DECIMAL(10,2))
                               ELSE 0
                           END
                       ) AS total_tax_amount
                FROM booking_transactions bt
                CROSS JOIN (SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS idx
                WHERE JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \']\'))) IS NOT NULL
                GROUP BY bt.booking_id
            ) AS tx_sum'), 'bookings.id', '=', 'tx_sum.booking_id')
            ->where('bookings.status', 'completed')
            ->groupBy(DB::raw('DATE(bookings.start_date_time)'));
    }

    public static function totalservice($taxAmount)
    {
        return self::select(
            DB::raw('DATE(bookings.start_date_time) AS start_date_time'),
            DB::raw('COUNT(DISTINCT bookings.id) AS total_bookings'),
            DB::raw('COALESCE(SUM(booking_services.service_price), 0) as total_service_amount'),
            DB::raw('
                COALESCE(SUM(booking_services.service_price), 0) +
                ' . $taxAmount . ' AS total_amount')
        )
            ->leftJoin('booking_services', 'bookings.id', '=', 'booking_services.booking_id')
            ->where('bookings.status', 'completed')
            ->groupBy(DB::raw('DATE(bookings.start_date_time)'));
    }

    public static function overallReport()
    {
        return self::select(
            'bookings.id as id',
            'bookings.start_date_time',
            DB::raw('COALESCE(bs.total_service_amount, 0) as total_service_amount'),
            DB::raw('COALESCE(bs.total_service, 0) as total_service'),
            DB::raw('COALESCE(tx_sum.total_tax_amount, 0) as total_tax_amount'),
            DB::raw('(COALESCE(bs.total_service_amount, 0) + COALESCE(tx_sum.total_tax_amount, 0)) as total_amount')
        )
            ->leftJoin(DB::raw('(
                SELECT booking_id,
                       SUM(service_price) as total_service_amount,
                       COUNT(service_id) as total_service
                FROM booking_services
                GROUP BY booking_id
            ) AS bs'), 'bookings.id', '=', 'bs.booking_id')
            ->leftJoin(DB::raw('(
                SELECT bt.booking_id,
                       SUM(
                           CASE
                               WHEN JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].type\'))) = \'percent\'
                                   THEN (SELECT COALESCE(SUM(bs_in.service_price), 0) FROM booking_services bs_in WHERE bs_in.booking_id = bt.booking_id) * CAST(JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].percent\'))) AS DECIMAL(10,2)) / 100
                               WHEN JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].type\'))) = \'fixed\'
                                   THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \'].tax_amount\'))) AS DECIMAL(10,2))
                               ELSE 0
                           END
                       ) AS total_tax_amount
                FROM booking_transactions bt
                CROSS JOIN (SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS idx
                WHERE JSON_UNQUOTE(JSON_EXTRACT(bt.tax_percentage, CONCAT(\'$[\', idx.i, \']\'))) IS NOT NULL
                GROUP BY bt.booking_id
            ) AS tx_sum'), 'bookings.id', '=', 'tx_sum.booking_id')
            ->where('bookings.status', 'completed');
    }

    public function calculateServiceDuration()
    {
        $bookingServiceDuration = BookingService::where('booking_id', $this->id)
            ->sum('duration_min');

        if ($bookingServiceDuration > 0) {
            return $bookingServiceDuration;
        }

        // return BookingPackageService::where('booking_id', $this->id)->with('services')->sum('services.duration_min');
        $bookingPackageServices = BookingPackageService::where('booking_id', $this->id)
            ->with('services')
            ->get();

        $totalDuration = $bookingPackageServices->sum(function ($bookingService) {
            return $bookingService->services->duration_min ?? 0;
        });
        return $totalDuration;
    }

    public function userPackageServices()
    {
        return $this->hasManyThrough(
            UserPackageServices::class, // Target model
            BookingPackages::class, // Intermediate model
            'booking_id', // Foreign key on BookingPackage
            'package_id', // Foreign key on UserPackageService
            'id', // Local key on Booking
            'package_id' // Local key on BookingPackage
        )->with('packageService.services');
    }

    public function bookedPackageService()
    {
        return $this->hasMany(BookingPackageService::class, 'booking_id', 'id');
    }

    protected static function booted()
    {
        static::updated(function ($booking) {
            if ($booking->isDirty('status') && $booking->status === 'check_out') {
                \App\Jobs\SendPostServiceEvaluationWhatsAppJob::dispatch($booking->id)->delay(now()->addMinutes(10));
            }
        });
    }
}
