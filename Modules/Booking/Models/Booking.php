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
            $tenMinutesAgo = \Carbon\Carbon::now()->subMinutes(10);
            $expiredBookings = static::where('status', 'pending')
                ->whereIn('payment_type', ['cart', 'payment'])
                ->where('created_at', '<=', $tenMinutesAgo)
                ->unpaid()
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
        static::cleanupExpiredBookings();

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
        $bsSub = DB::table('booking_services')
            ->select('booking_id', DB::raw('COUNT(*) as total_service'), DB::raw('SUM(service_price) as total_service_amount'))
            ->groupBy('booking_id');

        $txSub = DB::table('booking_transactions as bt')
            ->select('bt.booking_id', DB::raw('
                SUM(CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.type\')) = \'percent\'
                    THEN (SELECT COALESCE(SUM(service_price), 0) FROM booking_services WHERE booking_id = bt.booking_id) * CAST(JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.percent\')) AS DECIMAL(10,2)) / 100
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.type\')) = \'fixed\'
                    THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.tax_amount\')) AS DECIMAL(10,2))
                    ELSE 0
                END) as total_tax_amount
            '))
            ->join(DB::raw('(
                SELECT id, booking_id, JSON_EXTRACT(tax_percentage, CONCAT(\'$[\', idx, \']\')) AS tax_item
                FROM booking_transactions
                CROSS JOIN (SELECT 0 AS idx UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS idxs
                WHERE tax_percentage IS NOT NULL AND JSON_VALID(tax_percentage) AND idx < JSON_LENGTH(tax_percentage)
            ) AS jt'), 'bt.id', '=', 'jt.id')
            ->groupBy('bt.booking_id');

        return self::select(
            DB::raw('DATE(bookings.start_date_time) AS start_date_time'),
            DB::raw('COUNT(bookings.id) AS total_booking'),
            DB::raw('COALESCE(SUM(bs_agg.total_service), 0) AS total_service'),
            DB::raw('COALESCE(SUM(bs_agg.total_service_amount), 0) AS total_service_amount'),
            DB::raw('COALESCE(SUM(tx_agg.total_tax_amount), 0) AS total_tax_amount'),
            DB::raw('COALESCE(SUM(bs_agg.total_service_amount), 0) + COALESCE(SUM(tx_agg.total_tax_amount), 0) AS total_amount')
        )
            ->leftJoinSub($bsSub, 'bs_agg', 'bookings.id', '=', 'bs_agg.booking_id')
            ->leftJoinSub($txSub, 'tx_agg', 'bookings.id', '=', 'tx_agg.booking_id')
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
                ' . (float) $taxAmount . ' AS total_amount')
        )
            ->leftJoin('booking_services', 'bookings.id', '=', 'booking_services.booking_id')
            ->where('bookings.status', 'completed')
            ->groupBy(DB::raw('DATE(bookings.start_date_time)'));
    }

    public static function overallReport()
    {
        $bsSub = DB::table('booking_services')
            ->select('booking_id', DB::raw('COUNT(*) as total_service'), DB::raw('SUM(service_price) as total_service_amount'))
            ->groupBy('booking_id');

        $txSub = DB::table('booking_transactions as bt')
            ->select('bt.booking_id', DB::raw('
                SUM(CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.type\')) = \'percent\'
                    THEN (SELECT COALESCE(SUM(service_price), 0) FROM booking_services WHERE booking_id = bt.booking_id) * CAST(JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.percent\')) AS DECIMAL(10,2)) / 100
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.type\')) = \'fixed\'
                    THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(jt.tax_item, \'$.tax_amount\')) AS DECIMAL(10,2))
                    ELSE 0
                END) as total_tax_amount
            '))
            ->join(DB::raw('(
                SELECT id, booking_id, JSON_EXTRACT(tax_percentage, CONCAT(\'$[\', idx, \']\')) AS tax_item
                FROM booking_transactions
                CROSS JOIN (SELECT 0 AS idx UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS idxs
                WHERE tax_percentage IS NOT NULL AND JSON_VALID(tax_percentage) AND idx < JSON_LENGTH(tax_percentage)
            ) AS jt'), 'bt.id', '=', 'jt.id')
            ->groupBy('bt.booking_id');

        return self::select(
            'bookings.id as id',
            DB::raw('COALESCE(bs_agg.total_service_amount, 0) as total_service_amount'),
            DB::raw('COALESCE(bs_agg.total_service, 0) as total_service'),
            DB::raw('COALESCE(tx_agg.total_tax_amount, 0) as total_tax_amount'),
            DB::raw('COALESCE(bs_agg.total_service_amount, 0) + COALESCE(tx_agg.total_tax_amount, 0) as total_amount'),
            'bookings.start_date_time'
        )
            ->leftJoinSub($bsSub, 'bs_agg', 'bookings.id', '=', 'bs_agg.booking_id')
            ->leftJoinSub($txSub, 'tx_agg', 'bookings.id', '=', 'tx_agg.booking_id')
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


}
