<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Package\Models\BookingPackages;
use Modules\Booking\Models\BookingTransaction;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderGroup;
use App\Models\GiftCard;


class BackendController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $global_booking = false;
        $today = Carbon::today();
        $action = $request->action ?? 'reset';
        if (isset($request->date_range) && $action !== 'reset') {
            $dates = explode(' to ', $request->date_range);
            if (count($dates) == 2) {
                $startDate = $dates[0] ?? date('Y-m-d');
                $endDate = $dates[1] ?? date('Y-m-d');
            } elseif (count($dates) == 1) {
                $startDate = $dates[0] ?? date('Y-m-d');
                $endDate = $startDate;
            } else {
                $startDate = Carbon::now()->subDays(10)->toDateString();
                $endDate = Carbon::now()->toDateString();
            }
        } else {
            $startDate = Carbon::now()->subDays(10)->toDateString();
            $endDate = Carbon::now()->toDateString();
        }

        // Previous period for comparison
        $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $prevStartDate = Carbon::parse($startDate)->subDays($daysDiff)->toDateString();
        $prevEndDate = Carbon::parse($startDate)->subDay()->toDateString();

        $date_range = $startDate . ' to ' . $endDate;
        $data = [
            'total_appointments'   => 0,
            'total_commission'     => 0,
            'total_revenue'        => 0,
            'total_new_customers'  => 0,
            'upcomming_appointments' => [],
            'top_services'         => [],
            'revenue_chart'        => [],
            'total_orders'         => 0,
            'product_sales'        => 0,
        ];

        $totalServices = BookingService::whereHas('booking', function ($query) use ($startDate, $endDate) {
            $query->where(function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date_time', '>=', $startDate)
                    ->whereDate('start_date_time', '<=', $endDate);
            })->branch();
        });

        $data['total_appointments'] = Booking::where(function ($query) use ($startDate, $endDate) {
            $query->where(function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date_time', '>=', $startDate)
                    ->whereDate('start_date_time', '<=', $endDate);
            });
        })->where('status', 'completed')->branch()->count();

        $totalCommissionRaw = (float) Booking::with('commission')->branch()
            ->whereDate('start_date_time', '>=', $startDate)
            ->whereDate('start_date_time', '<=', $endDate)
            ->get()
            ->sum(function ($booking) {
                return $booking->commission->commission_amount ?? 0;
            });

        $data['total_commission_raw'] = $totalCommissionRaw;
        $data['total_commission']     = \Currency::format($totalCommissionRaw);

        $bookings = BookingTransaction::with('booking')->whereHas('booking', function ($query) use ($startDate, $endDate) {
            $query->where(function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date_time', '>=', $startDate)
                    ->whereDate('start_date_time', '<=', $endDate)
                    ->where('status', 'completed');
            })->branch();
        })->pluck('booking_id')->toArray();

        $totalServiceAmount = BookingService::whereIn('booking_id', $bookings)->sum('service_price');
        $totalPackageAmount = BookingPackages::whereIn('booking_id', $bookings)->sum('package_price');
        $totalgiftServices = GiftCard::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('payment_status', 1)
            ->sum('subtotal');

        $orders = Order::where(function ($q) {
            $q->orWhereIn('order_group_id', OrderGroup::pluck('id'));
        });

        $data['total_orders'] = $orders->where('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)->count();

        $productRevenue = (float) OrderGroup::where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('grand_total_amount');

        $data['product_sales_raw'] = $productRevenue;
        $data['product_sales']     = \Currency::format($productRevenue);

        $currRevenue = (float) $totalServiceAmount + (float) $totalPackageAmount + (float) $totalgiftServices + (float) $productRevenue;
        $data['total_revenue_raw'] = $currRevenue;
        $data['total_revenue']     = \Currency::format($currRevenue);

        $netRevenue = max(0, $currRevenue - $totalCommissionRaw);
        $data['net_revenue_raw']   = $netRevenue;
        $data['net_revenue']       = \Currency::format($netRevenue);

        $data['avg_booking_value'] = $data['total_appointments'] > 0
            ? round($currRevenue / $data['total_appointments'], 2) : 0;
        $data['avg_booking_value_formatted'] = \Currency::format($data['avg_booking_value']);

        $data['total_new_customers'] = User::where('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'user');
            })
            ->count();

        $datetime = Carbon::now()->setTimezone(setting('default_time_zone') ?? 'UTC');

        $data['upcomming_appointments'] = Booking::with('branch', 'user', 'services')
            ->where('start_date_time', '>=', $datetime)
            ->orderBy('start_date_time')
            ->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->whereHas('branch', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->branch()
            ->take(10)
            ->get();

        $data['top_services'] = $totalServices->with('service')->select(
            'service_id',
            \DB::raw('COUNT(*) as total_service_count'),
            \DB::raw('SUM(service_price) as total_service_price')
        )
            ->groupBy('service_id')
            ->orderByDesc('total_service_price')
            ->limit(5)
            ->get();

        $chartBookingRevenue = Booking::select(
            \DB::raw('DATE(bookings.start_date_time) AS booking_date'),
            \DB::raw('SUM(booking_services.service_price) AS total_price'),
            \DB::raw('COUNT(DISTINCT booking_services.booking_id) AS total_booking')
        )
            ->leftJoin('booking_services', 'bookings.id', '=', 'booking_services.booking_id')
            ->whereDate('bookings.start_date_time', '>=', $startDate)
            ->whereDate('bookings.start_date_time', '<=', $endDate)
            ->where('status', 'completed')
            ->branch()
            ->groupBy(\DB::raw('DATE(bookings.start_date_time)'))
            ->get();

        $data['revenue_chart']['xaxis']          = $chartBookingRevenue?->pluck('booking_date')->toArray() ?? [];
        $data['revenue_chart']['total_bookings'] = $chartBookingRevenue?->pluck('total_booking')->toArray() ?? [];
        $data['revenue_chart']['total_price']    = $chartBookingRevenue?->pluck('total_price')->toArray() ?? [];

        // ============================================================
        // EXECUTIVE ANALYTICS — Additional Financial & Admin KPIs
        // ============================================================

        // 1. Booking Status Breakdown (for donut chart)
        $bookingStatusCounts = Booking::query()->branch()
            ->whereDate('start_date_time', '>=', $startDate)
            ->whereDate('start_date_time', '<=', $endDate)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $data['booking_status'] = [
            'completed' => (int) ($bookingStatusCounts['completed'] ?? 0),
            'pending'   => (int) ($bookingStatusCounts['pending']   ?? 0),
            'cancelled' => (int) ($bookingStatusCounts['cancelled'] ?? 0),
            'confirmed' => (int) ($bookingStatusCounts['confirmed'] ?? 0),
        ];

        $totalBookingsInPeriod = array_sum($bookingStatusCounts);
        $cancelledCount        = $bookingStatusCounts['cancelled'] ?? 0;
        $data['cancellation_rate']     = $totalBookingsInPeriod > 0
            ? round(($cancelledCount / $totalBookingsInPeriod) * 100, 1) : 0;
        $data['fulfillment_rate']      = $totalBookingsInPeriod > 0
            ? round(($data['total_appointments'] / $totalBookingsInPeriod) * 100, 1) : 0;
        $data['total_bookings_period'] = $totalBookingsInPeriod;

        // 2. Revenue Breakdown by Source
        $data['revenue_breakdown'] = [
            'services'  => round((float) $totalServiceAmount, 2),
            'packages'  => round((float) $totalPackageAmount, 2),
            'giftcards' => round((float) $totalgiftServices, 2),
            'products'  => round($productRevenue, 2),
        ];

        // 3. Period-over-period comparisons
        $prevCompletedBookings = Booking::query()->branch()
            ->whereDate('start_date_time', '>=', $prevStartDate)
            ->whereDate('start_date_time', '<=', $prevEndDate)
            ->where('status', 'completed')
            ->count();

        $prevNewCustomers = User::whereDate('created_at', '>=', $prevStartDate)
            ->whereDate('created_at', '<=', $prevEndDate)
            ->whereHas('roles', fn($q) => $q->where('name', 'user'))
            ->count();

        $prevBookingIds = BookingTransaction::with('booking')
            ->whereHas('booking', function ($q) use ($prevStartDate, $prevEndDate) {
                $q->whereDate('start_date_time', '>=', $prevStartDate)
                    ->whereDate('start_date_time', '<=', $prevEndDate)
                    ->where('status', 'completed')
                    ->branch();
            })->pluck('booking_id')->toArray();

        $prevServiceAmount = BookingService::whereIn('booking_id', $prevBookingIds)->sum('service_price');
        $prevPackageAmount = BookingPackages::whereIn('booking_id', $prevBookingIds)->sum('package_price');
        $prevGiftAmount    = GiftCard::whereDate('created_at', '>=', $prevStartDate)
            ->whereDate('created_at', '<=', $prevEndDate)
            ->where('payment_status', 1)->sum('subtotal');

        $prevRevenue = (float) $prevServiceAmount + (float) $prevPackageAmount + (float) $prevGiftAmount;

        $data['comparison'] = [
            'revenue_change'   => $prevRevenue > 0
                ? round((($currRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0,
            'bookings_change'  => $prevCompletedBookings > 0
                ? round((($data['total_appointments'] - $prevCompletedBookings) / $prevCompletedBookings) * 100, 1)
                : 0,
            'customers_change' => $prevNewCustomers > 0
                ? round((($data['total_new_customers'] - $prevNewCustomers) / $prevNewCustomers) * 100, 1)
                : 0,
        ];

        // 4. Top Staff Productivity Leaderboard (via BookingService)
        $topStaffList = BookingService::whereHas('booking', function ($q) use ($startDate, $endDate) {
                $q->whereDate('start_date_time', '>=', $startDate)
                    ->whereDate('start_date_time', '<=', $endDate)
                    ->where('status', 'completed')
                    ->branch();
            })
            ->whereNotNull('employee_id')
            ->with('employee')
            ->selectRaw('employee_id, COUNT(*) as total_bookings, SUM(service_price) as total_revenue')
            ->groupBy('employee_id')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        $data['top_staff'] = $topStaffList;
        $data['staff_chart_names'] = $topStaffList->map(function ($s) {
            $emp = $s->employee;
            if (!$emp) return 'Staff #' . $s->employee_id;
            $name = trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''));
            return $name ?: ($emp->username ?? 'Staff');
        })->toArray();
        $data['staff_chart_revenue'] = $topStaffList->pluck('total_revenue')->map(fn($v) => round((float)$v, 2))->toArray();

        // 5. Gift card sales count
        $data['gift_cards_sold'] = GiftCard::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('payment_status', 1)
            ->count();

        // 6. Branch Performance Comparison
        $currentLocale = app()->getLocale();
        $branches = \App\Models\Branch::where('status', 1)->get();
        $branchNames    = [];
        $branchRevenues = [];
        $branchBookings = [];

        foreach ($branches as $branch) {
            $bName = $branch->getTranslation('name', $currentLocale) ?: $branch->getTranslation('name', 'ar') ?: 'Branch #' . $branch->id;
            $bBookings = Booking::where('branch_id', $branch->id)
                ->whereDate('start_date_time', '>=', $startDate)
                ->whereDate('start_date_time', '<=', $endDate)
                ->where('status', 'completed')
                ->count();
            $bRevenue = (float) BookingService::whereHas('booking', function ($q) use ($branch, $startDate, $endDate) {
                $q->where('branch_id', $branch->id)
                    ->whereDate('start_date_time', '>=', $startDate)
                    ->whereDate('start_date_time', '<=', $endDate)
                    ->where('status', 'completed');
            })->sum('service_price');

            $branchNames[]    = $bName;
            $branchRevenues[] = round($bRevenue, 2);
            $branchBookings[] = $bBookings;
        }

        $data['branch_chart_names']    = $branchNames;
        $data['branch_chart_revenue']  = $branchRevenues;
        $data['branch_chart_bookings'] = $branchBookings;

        // 7. Peak Booking Hours Distribution (9:00 AM to 10:00 PM)
        $hourlyCounts = Booking::query()->branch()
            ->whereDate('start_date_time', '>=', $startDate)
            ->whereDate('start_date_time', '<=', $endDate)
            ->where('status', 'completed')
            ->selectRaw('HOUR(start_date_time) as hr, COUNT(*) as cnt')
            ->groupBy('hr')
            ->pluck('cnt', 'hr')
            ->toArray();

        $peakLabels = [];
        $peakData   = [];
        for ($h = 9; $h <= 22; $h++) {
            $formattedHour = $h > 12 ? ($h - 12) . ':00 م' : ($h == 12 ? '12:00 م' : $h . ':00 ص');
            $peakLabels[]  = $formattedHour;
            $peakData[]    = (int) ($hourlyCounts[$h] ?? 0);
        }
        $data['peak_hours_labels'] = $peakLabels;
        $data['peak_hours_data']   = $peakData;

        // 8. Payment Methods Breakdown
        $paymentCounts = Booking::query()->branch()
            ->whereDate('start_date_time', '>=', $startDate)
            ->whereDate('start_date_time', '<=', $endDate)
            ->where('status', 'completed')
            ->selectRaw('COALESCE(payment_type, "cash") as p_type, COUNT(*) as cnt')
            ->groupBy('p_type')
            ->pluck('cnt', 'p_type')
            ->toArray();

        $paymentLabels = [];
        $paymentSeries = [];
        foreach ($paymentCounts as $type => $count) {
            $label = match (strtolower((string) $type)) {
                'cash'       => 'نقدي (Cash)',
                'cart', 'card' => 'بطاقة مدى / ائتمان',
                'tap'        => 'Tap بوابة الدفع',
                'wallet'     => 'المحفظة الإلكترونية',
                'apple_pay', 'applepay' => 'Apple Pay',
                default      => ucfirst(str_replace('_', ' ', (string) $type)),
            };
            $paymentLabels[] = $label;
            $paymentSeries[] = (int) $count;
        }

        $data['payment_chart_labels'] = $paymentLabels ?: ['نقدي', 'إلكتروني'];
        $data['payment_chart_series'] = $paymentSeries ?: [1, 0];

        // Raw comparison amounts
        $data['revenue_breakdown_raw'] = $currRevenue;
        $data['prev_revenue_raw']      = $prevRevenue;

        return view('backend.index', compact('data', 'date_range', 'global_booking'));
    }

    public function setCurrentBranch($branch_id)
    {
        request()->session()->forget('selected_branch');
        request()->session()->put('selected_branch', $branch_id);
        return redirect()->back()->with('success', 'Current Branch Has Been Changes')->withInput();
    }

    public function resetBranch()
    {
        request()->session()->forget('selected_branch');
        return redirect()->back()->with('success', 'Show All Branch Content')->withInput();
    }

    public function setUserSetting(Request $request)
    {
        auth()->user()->update(['user_setting' => $request->settings]);
        return response()->json(['status' => true]);
    }
}
