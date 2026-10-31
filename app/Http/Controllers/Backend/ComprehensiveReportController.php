<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BookingReview;
use App\Models\Branch;
use App\Models\GiftCard;
use App\Models\Invoice;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyPointTransaction;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingPaymentLink;
use Modules\Product\Models\OrderGroup;
use Modules\Promotion\Models\UserCouponRedeem;
use Modules\Service\Models\Service;

/**
 * One-page overview of the whole business for a date range (and optional branch):
 * bookings, revenue, payments, customers, staff, services, coupons, loyalty, reviews, gifts, orders.
 *
 * Bookings are counted by appointment date; money (invoices, gifts, orders, payment attempts,
 * coupons, loyalty) by the date it was created. Sections without a branch (invoices, gifts,
 * orders, loyalty, coupons, customers) ignore the branch filter.
 */
class ComprehensiveReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_financial_report|daily_booking_report');

        view()->share(['module_icon' => 'fa-solid fa-chart-pie']);
    }

    public function index(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $data = [
            'module_title' => 'Reports',
            'module_action' => 'List',
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'branchId' => $branchId,
            'branches' => Branch::orderBy('id')->get(),
            'bookings' => $this->bookingsSection($from, $to, $branchId),
            'revenue' => $this->revenueSection($from, $to),
            'payments' => $this->paymentsSection($from, $to),
            'customers' => $this->customersSection($from, $to),
            'staff' => $this->staffSection($from, $to, $branchId),
            'services' => $this->servicesSection($from, $to, $branchId),
            'coupons' => $this->couponsSection($from, $to),
            'loyalty' => $this->loyaltySection($from, $to),
            'reviews' => $this->reviewsSection($from, $to, $branchId),
            'extras' => $this->extrasSection($from, $to),
            'trend' => $this->trendSection($from, $to, $branchId),
        ];

        return view('backend.reports.comprehensive-report', $data);
    }

    private function resolveRange(Request $request): array
    {
        $preset = (string) $request->get('preset', '');
        $today = Carbon::today();

        switch ($preset) {
            case 'today':
                return [$today->copy()->startOfDay(), $today->copy()->endOfDay(), $preset];
            case '7days':
                return [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay(), $preset];
            case 'year':
                return [$today->copy()->startOfYear(), $today->copy()->endOfDay(), $preset];
            case 'month':
                return [$today->copy()->startOfMonth(), $today->copy()->endOfDay(), $preset];
        }

        try {
            $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : $today->copy()->startOfMonth();
            $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : $today->copy()->endOfDay();
        } catch (\Throwable $e) {
            $from = $today->copy()->startOfMonth();
            $to = $today->copy()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, 'custom'];
    }

    private function bookingsBase(Carbon $from, Carbon $to, ?int $branchId)
    {
        return Booking::query()
            ->whereBetween('start_date_time', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }

    private function bookingsSection(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $base = $this->bookingsBase($from, $to, $branchId);

        $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
        $total = array_sum($byStatus);
        $paid = (clone $base)->paid()->count();

        // Value of services on paid bookings, per branch.
        $byBranch = $this->paidServiceLines($from, $to, $branchId)
            ->selectRaw('bookings.branch_id as branch_id, COUNT(DISTINCT bookings.id) as bookings_count, SUM(booking_services.service_price - COALESCE(booking_services.discount_amount, 0)) as amount')
            ->groupBy('bookings.branch_id')
            ->orderByDesc('amount')
            ->get();
        $branchNames = Branch::whereIn('id', $byBranch->pluck('branch_id')->filter())->get()->keyBy('id');

        return [
            'total' => $total,
            'paid' => $paid,
            'unpaid' => max($total - $paid, 0),
            'by_status' => $byStatus,
            'by_branch' => $byBranch,
            'branch_names' => $branchNames,
        ];
    }

    /** Service lines belonging to paid, non-deleted bookings in range. */
    private function paidServiceLines(Carbon $from, Carbon $to, ?int $branchId)
    {
        return DB::table('booking_services')
            ->join('bookings', 'bookings.id', '=', 'booking_services.booking_id')
            ->whereNull('bookings.deleted_at')
            ->whereNull('booking_services.deleted_at')
            ->whereBetween('bookings.start_date_time', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('bookings.branch_id', $branchId))
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('booking_transactions')
                    ->whereColumn('booking_transactions.booking_id', 'booking_services.booking_id')
                    ->where('booking_transactions.payment_status', 1)
                    ->whereNull('booking_transactions.deleted_at');
            });
    }

    private function revenueSection(Carbon $from, Carbon $to): array
    {
        $invoices = Invoice::query()->whereBetween('created_at', [$from, $to]);

        $totals = (clone $invoices)->selectRaw('COUNT(*) as total, COALESCE(SUM(final_total),0) as amount, COALESCE(SUM(discount_amount),0) as discounts, COALESCE(SUM(taxs_service),0) as tax')->first();

        $byMethod = (clone $invoices)
            ->selectRaw("COALESCE(NULLIF(payment_method, ''), 'other') as method, COUNT(*) as total, COALESCE(SUM(final_total),0) as amount")
            ->groupBy('method')
            ->orderByDesc('amount')
            ->get();

        $count = (int) $totals->total;

        return [
            'invoices' => $count,
            'amount' => (float) $totals->amount,
            'discounts' => (float) $totals->discounts,
            'tax' => (float) $totals->tax,
            'average' => $count > 0 ? round($totals->amount / $count, 2) : 0,
            'by_method' => $byMethod,
        ];
    }

    private function paymentsSection(Carbon $from, Carbon $to): array
    {
        $base = PaymentAttempt::query()->whereBetween('created_at', [$from, $to]);

        $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
        $byGateway = (clone $base)
            ->selectRaw("gateway, COUNT(*) as total, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid, COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END),0) as paid_amount")
            ->groupBy('gateway')
            ->get();

        $total = array_sum($byStatus);
        $paid = (int) ($byStatus['paid'] ?? 0);

        return [
            'total' => $total,
            'paid' => $paid,
            'success_rate' => $total > 0 ? round($paid / $total * 100, 1) : 0,
            'by_status' => $byStatus,
            'by_gateway' => $byGateway,
        ];
    }

    private function customersSection(Carbon $from, Carbon $to): array
    {
        $customers = User::role('user');

        return [
            'total' => (clone $customers)->count(),
            'new' => (clone $customers)->whereBetween('users.created_at', [$from, $to])->count(),
            'employees' => User::role('employee')->count(),
        ];
    }

    private function staffSection(Carbon $from, Carbon $to, ?int $branchId)
    {
        $rows = $this->paidServiceLines($from, $to, $branchId)
            ->whereNotNull('booking_services.employee_id')
            ->selectRaw('booking_services.employee_id as employee_id, COUNT(*) as services_count, SUM(booking_services.service_price - COALESCE(booking_services.discount_amount, 0)) as amount')
            ->groupBy('booking_services.employee_id')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return ['rows' => $rows, 'names' => User::whereIn('id', $rows->pluck('employee_id'))->get()->keyBy('id')];
    }

    private function servicesSection(Carbon $from, Carbon $to, ?int $branchId)
    {
        $rows = $this->paidServiceLines($from, $to, $branchId)
            ->selectRaw('booking_services.service_id as service_id, COUNT(*) as total, SUM(booking_services.service_price - COALESCE(booking_services.discount_amount, 0)) as amount')
            ->groupBy('booking_services.service_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return ['rows' => $rows, 'names' => Service::whereIn('id', $rows->pluck('service_id'))->get()->keyBy('id')];
    }

    private function couponsSection(Carbon $from, Carbon $to): array
    {
        $base = UserCouponRedeem::query()->whereBetween('created_at', [$from, $to]);

        return [
            'redeems' => (clone $base)->count(),
            'discount' => (float) (clone $base)->sum('discount'),
            'top' => (clone $base)
                ->selectRaw('coupon_code, COUNT(*) as total, COALESCE(SUM(discount),0) as discount')
                ->groupBy('coupon_code')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }

    private function loyaltySection(Carbon $from, Carbon $to): array
    {
        $byAction = LoyaltyPointTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('action, COUNT(*) as total, COALESCE(SUM(points),0) as points')
            ->groupBy('action')
            ->get()
            ->keyBy('action');

        return [
            'added' => (float) ($byAction['add']->points ?? 0),
            'by_action' => $byAction,
            'outstanding' => (float) LoyaltyPoint::sum('points'),
            'holders' => LoyaltyPoint::where('points', '>', 0)->count(),
        ];
    }

    private function reviewsSection(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $base = BookingReview::query()
            ->where('status', BookingReview::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        return [
            'total' => (clone $base)->count(),
            'avg_overall' => round((float) (clone $base)->avg('overall_rating'), 2),
            'avg_specialist' => round((float) (clone $base)->avg('specialist_rating'), 2),
            'avg_service' => round((float) (clone $base)->avg('service_rating'), 2),
            'website' => (clone $base)->where('source', 'website')->count(),
            'whatsapp' => (clone $base)->where('source', 'whatsapp')->count(),
        ];
    }

    private function extrasSection(Carbon $from, Carbon $to): array
    {
        $gifts = GiftCard::query()->where('payment_status', 1)->whereBetween('created_at', [$from, $to]);
        $orders = OrderGroup::query()->where('payment_status', 'paid')->whereBetween('created_at', [$from, $to]);
        $links = BookingPaymentLink::query()->whereBetween('created_at', [$from, $to]);

        return [
            'gift_cards' => (clone $gifts)->count(),
            'gift_amount' => (float) (clone $gifts)->sum('subtotal'),
            'orders' => (clone $orders)->count(),
            'orders_amount' => (float) (clone $orders)->sum('grand_total_amount'),
            'links_total' => (clone $links)->count(),
            'links_paid' => (clone $links)->where('status', BookingPaymentLink::STATUS_PAID)->count(),
            'links_paid_amount' => (float) (clone $links)->where('status', BookingPaymentLink::STATUS_PAID)->sum('amount'),
        ];
    }

    /** Daily bookings and invoiced revenue for the chart. */
    private function trendSection(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $bookings = $this->bookingsBase($from, $to, $branchId)
            ->selectRaw('DATE(start_date_time) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $revenue = Invoice::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(final_total),0) as amount')
            ->groupBy('day')
            ->pluck('amount', 'day');

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $limit = 0;
        while ($cursor->lte($to) && $limit++ < 400) {
            $key = $cursor->toDateString();
            $days[] = [
                'day' => $key,
                'bookings' => (int) ($bookings[$key] ?? 0),
                'revenue' => round((float) ($revenue[$key] ?? 0), 2),
            ];
            $cursor->addDay();
        }

        return $days;
    }
}
