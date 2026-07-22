<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Currency;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Booking\Models\Booking;
use Modules\Earning\Models\EmployeeEarning;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderGroup;
use Modules\Booking\Models\BookingTransaction;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Promotion;
use Modules\Promotion\Models\UserCouponRedeem;
use Yajra\DataTables\DataTables;

class ReportsController extends Controller
{
    public function __construct()
    {
        // Page Title
        $this->module_title = 'Reports';

        // module name
        $this->module_name = 'reports';

        // module icon
        $this->module_icon = 'fa-solid fa-chart-line';

        $this->middleware('permission:view_financial_report')->only(['financial_report']);
        $this->middleware('permission:view_coupon_report')->only(['coupon_report']);
        $this->middleware('permission:orders_report')->only(['order_report']);

        view()->share([
            'module_icon' => $this->module_icon,
        ]);
    }

    public function daily_booking_report(Request $request)
    {
        $module_title = __('report.title_daily_report');

        $module_name = 'daily-booking-report';
        $export_import = true;
        $export_columns = [
            [
                'value' => 'date',
                'text' => 'Date',
            ],
            [
                'value' => 'total_booking',
                'text' => 'No. Booking',
            ],
            [
                'value' => 'total_service',
                'text' => 'No. Services',
            ],
            [
                'value' => 'total_service_amount',
                'text' => 'Service Amount',
            ],
            [
                'value' => 'total_tax_amount',
                'text' => 'Tax Amount',
            ],
            [
                'value' => 'total_amount',
                'text' => 'Final Amount',
            ],
        ];
        $export_url = route('backend.reports.daily-booking-report-review');

        return view('backend.reports.daily-booking-report', compact('module_title', 'module_name', 'export_import', 'export_columns', 'export_url'));
    }

    public function sales_by_date_report(Request $request)
    {
        $module_title = __('report.title_sales_report');
        $module_name = 'sales-by-date-report';

        return view('backend.reports.sales-by-date-report', compact('module_title', 'module_name'));
    }

    public function sales_by_date_report_index_data(Request $request)
    {
        $preset = $request->input('preset', 'this_month');
        $customRange = $request->input('date_range');

        [$startDate, $endDate] = $this->resolveReportDates($preset, $customRange);

        $orderQuery = OrderGroup::query()->with('order.orderItems');
        if ($startDate) {
            $orderQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $orderQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }
        $orders = $orderQuery->get();

        $bookingQuery = BookingTransaction::with(['booking.services', 'booking.products', 'booking.bookingPackages'])
            ->where('payment_status', 1);
        if ($startDate) {
            $bookingQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $bookingQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }
        $bookings = $bookingQuery->get();

        $periodMap = [];
        if ($startDate && $endDate) {
            $cursor = clone $startDate;
            while ($cursor <= $endDate) {
                $dateStr = $cursor->format('Y-m-d');
                $periodMap[$dateStr] = [
                    'date' => $dateStr,
                    'orders_count' => 0,
                    'items_count' => 0,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'shipping_cost' => 0.0,
                    'coupons_value' => 0.0,
                    'refunds_amount' => 0.0,
                ];
                $cursor->addDay();
            }
        }

        foreach ($orders as $og) {
            $dateStr = optional($og->created_at)->format('Y-m-d');
            if (! isset($periodMap[$dateStr])) {
                $periodMap[$dateStr] = [
                    'date' => $dateStr,
                    'orders_count' => 0,
                    'items_count' => 0,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'shipping_cost' => 0.0,
                    'coupons_value' => 0.0,
                    'refunds_amount' => 0.0,
                ];
            }

            $itemsQty = $og->order && $og->order->orderItems ? $og->order->orderItems->sum('product_qty') : 0;
            $gross = (float) ($og->sub_total_amount ?? 0) + (float) ($og->total_shipping_cost ?? 0) + (float) ($og->total_tax_amount ?? 0);
            if ($gross <= 0) {
                $gross = (float) ($og->grand_total_amount ?? 0) + (float) ($og->total_coupon_discount_amount ?? 0);
            }
            $net = (float) ($og->grand_total_amount ?? 0);
            $shipping = (float) ($og->total_shipping_cost ?? 0);
            $coupons = (float) ($og->total_coupon_discount_amount ?? 0) + (float) ($og->total_discount_amount ?? 0);

            $periodMap[$dateStr]['orders_count'] += 1;
            $periodMap[$dateStr]['items_count'] += $itemsQty;
            $periodMap[$dateStr]['gross_sales'] += $gross;
            $periodMap[$dateStr]['net_sales'] += $net;
            $periodMap[$dateStr]['shipping_cost'] += $shipping;
            $periodMap[$dateStr]['coupons_value'] += $coupons;
        }

        foreach ($bookings as $tx) {
            $dateStr = optional($tx->created_at)->format('Y-m-d');
            if (! isset($periodMap[$dateStr])) {
                $periodMap[$dateStr] = [
                    'date' => $dateStr,
                    'orders_count' => 0,
                    'items_count' => 0,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'shipping_cost' => 0.0,
                    'coupons_value' => 0.0,
                    'refunds_amount' => 0.0,
                ];
            }

            $booking = $tx->booking;
            $serviceAmt = $booking && $booking->services ? $booking->services->sum('service_price') : 0;
            $productAmt = $booking && $booking->products ? $booking->products->sum(function ($p) {
                $price = $p->discounted_price && $p->discounted_price > 0 ? $p->discounted_price : $p->product_price;
                return $price * ($p->product_qty ?? 1);
            }) : 0;
            $pkgAmt = $booking && $booking->bookingPackages ? $booking->bookingPackages->sum('package_price') : 0;

            $itemsCount = ($booking && $booking->services ? $booking->services->count() : 0)
                + ($booking && $booking->products ? $booking->products->count() : 0)
                + ($booking && $booking->bookingPackages ? $booking->bookingPackages->count() : 0);

            $gross = $serviceAmt + $productAmt + $pkgAmt;
            $discount = (float) ($tx->discount_amount ?? 0);
            $net = max(0, $gross - $discount);

            $periodMap[$dateStr]['orders_count'] += 1;
            $periodMap[$dateStr]['items_count'] += $itemsCount;
            $periodMap[$dateStr]['gross_sales'] += $gross;
            $periodMap[$dateStr]['net_sales'] += $net;
            $periodMap[$dateStr]['coupons_value'] += $discount;
        }

        ksort($periodMap);
        $periodList = collect(array_values($periodMap));

        $totalGross = $periodList->sum('gross_sales');
        $totalNet = $periodList->sum('net_sales');
        $totalOrders = $periodList->sum('orders_count');
        $totalItems = $periodList->sum('items_count');
        $totalShipping = $periodList->sum('shipping_cost');
        $totalCoupons = $periodList->sum('coupons_value');
        $totalRefunds = $periodList->sum('refunds_amount');

        $chartCategories = $periodList->pluck('date')->toArray();
        $chartGross = $periodList->pluck('gross_sales')->map(fn ($v) => round($v, 2))->toArray();
        $chartNet = $periodList->pluck('net_sales')->map(fn ($v) => round($v, 2))->toArray();

        $tableRows = $periodList->map(function ($row) {
            return [
                'date' => $row['date'],
                'orders_count' => $row['orders_count'],
                'items_count' => $row['items_count'],
                'gross_sales' => Currency::format($row['gross_sales']),
                'net_sales' => Currency::format($row['net_sales']),
                'shipping_cost' => Currency::format($row['shipping_cost']),
                'coupons_value' => Currency::format($row['coupons_value']),
            ];
        });

        return response()->json([
            'summary' => [
                'gross_sales_formatted' => Currency::format($totalGross),
                'net_sales_formatted' => Currency::format($totalNet),
                'orders_count' => $totalOrders,
                'items_count' => $totalItems,
                'refund_amount_formatted' => Currency::format($totalRefunds),
                'shipping_cost_formatted' => Currency::format($totalShipping),
                'coupons_used_formatted' => Currency::format($totalCoupons),
            ],
            'chart' => [
                'categories' => $chartCategories,
                'gross_sales' => $chartGross,
                'net_sales' => $chartNet,
            ],
            'table_rows' => $tableRows,
        ]);
    }

    public function sales_by_date_report_export(Request $request)
    {
        $preset = $request->input('preset', 'this_month');
        $customRange = $request->input('date_range');

        [$startDate, $endDate] = $this->resolveReportDates($preset, $customRange);

        $columns = ['date', 'orders_count', 'items_count', 'gross_sales', 'net_sales', 'shipping_cost', 'coupons_value'];

        return \Excel::download(new \App\Exports\SalesByDateExport($columns, [$startDate, $endDate]), 'sales-by-date-report.csv');
    }

    private function resolveReportDates(string $preset, ?string $customRange): array
    {
        $now = Carbon::now();

        switch ($preset) {
            case 'last_7_days':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()];

            case 'last_month':
                $lastMonth = $now->copy()->subMonth();
                return [$lastMonth->copy()->startOfMonth()->startOfDay(), $lastMonth->copy()->endOfMonth()->endOfDay()];

            case 'this_year':
                return [$now->copy()->startOfYear()->startOfDay(), $now->copy()->endOfDay()];

            case 'custom':
                if ($customRange) {
                    return $this->parseDateRange($customRange);
                }
                return [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfDay()];

            case 'this_month':
            default:
                return [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfDay()];
        }
    }

    public function financial_report(Request $request)
    {
        $module_title = __('report.title_financial_report');
        $module_name = 'financial-report';

        return view('backend.reports.financial-report', compact('module_title', 'module_name'));
    }

    public function financial_report_index_data(Datatables $datatable, Request $request)
    {
        $filter = $request->filter ?? [];
        $reportType = $filter['report_type'] ?? 'daily';

        [$startDate, $endDate] = $this->parseDateRange($filter['date_range'] ?? []);

        $bookingQuery = BookingTransaction::with(['booking.services', 'booking.products', 'booking.bookingPackages'])
            ->where('payment_status', 1);

        if ($startDate) {
            $bookingQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $bookingQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $orderQuery = OrderGroup::query()->where('payment_status', 'paid');

        if ($startDate) {
            $orderQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $orderQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $bookingTransactions = $bookingQuery->get();
        $orderGroups = $orderQuery->get();
        $giftQuery = GiftCard::query()->where('payment_status', 1);

        if ($startDate) {
            $giftQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $giftQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $giftCards = $giftQuery->get();

        if ($reportType === 'custom') {
            $bookingTotal = $this->sumBookingRevenue($bookingTransactions);
            $orderTotal = $orderGroups->sum('grand_total_amount');
            $giftTotal = $giftCards->sum(function ($gift) {
                return (float) ($gift->subtotal ?? 0);
            });

            $rows = [[
                'period' => $this->formatRangeLabel($startDate, $endDate),
                'bookings_total' => Currency::format($bookingTotal),
                'orders_total' => Currency::format($orderTotal),
                'giftcards_total' => Currency::format($giftTotal),
                'grand_total' => Currency::format($bookingTotal + $orderTotal + $giftTotal),
            ]];

            return $datatable->collection(collect($rows))->toJson();
        }

        $bookingByPeriod = $bookingTransactions->groupBy(function ($tx) use ($reportType) {
            $date = $tx->created_at;
            return $reportType === 'monthly'
                ? $date->format('Y-m')
                : $date->format('Y-m-d');
        });

        $orderByPeriod = $orderGroups->groupBy(function ($order) use ($reportType) {
            $date = $order->created_at;
            return $reportType === 'monthly'
                ? $date->format('Y-m')
                : $date->format('Y-m-d');
        });
        $giftByPeriod = $giftCards->groupBy(function ($gift) use ($reportType) {
            $date = $gift->created_at;
            return $reportType === 'monthly'
                ? $date->format('Y-m')
                : $date->format('Y-m-d');
        });

        $periods = collect()
            ->merge($bookingByPeriod->keys())
            ->merge($orderByPeriod->keys())
            ->merge($giftByPeriod->keys())
            ->unique()
            ->sort()
            ->values();

        $rows = $periods->map(function ($period) use ($bookingByPeriod, $orderByPeriod, $giftByPeriod) {
            $bookingTotal = $this->sumBookingRevenue($bookingByPeriod->get($period, collect()));
            $orderTotal = $orderByPeriod->get($period, collect())->sum('grand_total_amount');
            $giftTotal = $giftByPeriod->get($period, collect())->sum(function ($gift) {
                return (float) ($gift->subtotal ?? 0);
            });

            return [
                'period' => $period,
                'bookings_total' => Currency::format($bookingTotal),
                'orders_total' => Currency::format($orderTotal),
                'giftcards_total' => Currency::format($giftTotal),
                'grand_total' => Currency::format($bookingTotal + $orderTotal + $giftTotal),
            ];
        });

        return $datatable->collection($rows)->toJson();
    }

    private function sumBookingRevenue($transactions): float
    {
        return $transactions->sum(function ($tx) {
            $booking = $tx->booking;
            if (! $booking) {
                return 0;
            }

            $serviceAmount = $booking->services ? $booking->services->sum('service_price') : 0;
            $productAmount = $booking->products ? $booking->products->sum(function ($product) {
                $price = $product->discounted_price && $product->discounted_price > 0
                    ? $product->discounted_price
                    : $product->product_price;
                return $price * ($product->product_qty ?? 1);
            }) : 0;
            $packageAmount = $booking->bookingPackages ? $booking->bookingPackages->sum('package_price') : 0;
            $discount = $tx->discount_amount ?? 0;

            return $serviceAmount + $productAmount + $packageAmount - $discount;
        });
    }

    private function parseDateRange($range): array
    {
        $start = null;
        $end = null;

        if (is_string($range)) {
            $range = explode(' to ', $range);
        }

        if (is_array($range) && isset($range[0]) && $range[0] !== '') {
            $start = Carbon::createFromFormat('d-m-Y', trim($range[0]))->startOfDay();
        }
        if (is_array($range) && isset($range[1]) && $range[1] !== '') {
            $end = Carbon::createFromFormat('d-m-Y', trim($range[1]))->endOfDay();
        }

        return [$start, $end];
    }

    private function formatRangeLabel($startDate, $endDate): string
    {
        if (! $startDate && ! $endDate) {
            return __('report.lbl_all_period');
        }

        if ($startDate && $endDate) {
            return $startDate->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d');
        }

        if ($startDate) {
            return $startDate->format('Y-m-d');
        }

        return $endDate->format('Y-m-d');
    }

    public function coupon_report(Request $request)
    {
        $module_title = __('report.title_coupon_report');
        $module_name = 'coupon-report';

        $coupons = Coupon::query()
            ->select('id', 'coupon_code')
            ->orderBy('coupon_code')
            ->get();

        return view('backend.reports.coupon-report', compact('module_title', 'module_name', 'coupons'));
    }

    public function coupon_report_index_data(Datatables $datatable, Request $request)
    {
        $query = UserCouponRedeem::with(['user', 'coupon.promotion', 'invoice']);

        $filter = $request->filter;

        if (isset($filter['coupon_id']) && $filter['coupon_id'] !== '') {
            $query->where('coupon_id', $filter['coupon_id']);
        }

        if (isset($filter['coupon_date'][0]) && $filter['coupon_date'][0] !== '') {
            $startDate = $filter['coupon_date'][0];
            $endDate = $filter['coupon_date'][1] ?? null;

            if ($endDate) {
                $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($startDate)));
                $query->whereDate('created_at', '<=', date('Y-m-d', strtotime($endDate)));
            } else {
                $query->whereDate('created_at', date('Y-m-d', strtotime($startDate)));
            }
        }

        return $datatable->eloquent($query)
            ->addIndexColumn()
            ->addColumn('coupon_code', function ($data) {
                return optional($data->coupon)->coupon_code ?? $data->coupon_code ?? '-';
            })
            ->addColumn('promotion_name', function ($data) {
                return optional(optional($data->coupon)->promotion)->name ?? '-';
            })
            ->addColumn('customer_name', function ($data) {
                $user = $data->user;
                $Profile_image = optional($user)->profile_image ?? default_user_avatar();
                $name = optional($user)->full_name ?? default_user_name();
                $phone = optional($user)->mobile ?? '--';
                return '
                    <div class="d-flex align-items-center text-decoration-none" style="color:#c39b61;">
                        <div class="me-3">
                            <img src="' . $Profile_image . '" class="avatar avatar-md rounded-circle" alt="' . $name . '" width="40" height="40">
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fw-bold">' . $name . '</span>
                            <small class="text-muted">' . $phone . '</small>
                        </div>
                    </div>
                ';
            })
            ->addColumn('invoice_id', function ($data) {
                $invoice = $data->invoice ?: $this->resolveCouponRedeemInvoice($data);

                if (! $invoice) {
                    return '-';
                }

                $url = route('app.invoice', ['invoice_id' => $invoice->id]) . '#invoice-card-' . $invoice->id;

                return '<a href="' . $url . '">INV-' . $invoice->id . '</a>';
            })
            ->addColumn('discount_amount', function ($data) {
                return Currency::format($data->discount ?? 0);
            })
            ->addColumn('note', function ($data) {
                $invoice = $data->invoice ?: $this->resolveCouponRedeemInvoice($data);

                if (! $invoice) {
                    return __('report.lbl_coupon_note_single_service');
                }
            })
            ->editColumn('created_at', function ($data) {
                return customDate($data->created_at);
            })
            ->rawColumns(['customer_name', 'invoice_id'])
            ->toJson();
    }

    private function resolveCouponRedeemInvoice(UserCouponRedeem $redeem): ?Invoice
    {
        if ($redeem->booking_id) {
            $invoice = Invoice::query()
                ->where(function ($query) use ($redeem) {
                    $query->whereJsonContains('cart_ids', (int) $redeem->booking_id)
                        ->orWhereJsonContains('cart_ids', (string) $redeem->booking_id);
                })
                ->latest('id')
                ->first();

            if ($invoice) {
                return $invoice;
            }
        }

        if ($redeem->user_id && $redeem->coupon_code) {
            return Invoice::query()
                ->where('user_id', $redeem->user_id)
                ->where('coupon_code', $redeem->coupon_code)
                ->latest('id')
                ->first();
        }

        return null;
    }

    public function promotion_report(Request $request)
    {
        $module_title = __('report.title_promotion_report');
        $module_name = 'promotion-report';

        $promotions = Promotion::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('backend.reports.promotion-report', compact('module_title', 'module_name', 'promotions'));
    }

    public function promotion_report_index_data(Datatables $datatable, Request $request)
    {
        $query = UserCouponRedeem::with(['user', 'coupon.promotion']);

        $filter = $request->filter;

        if (isset($filter['promotion_id']) && $filter['promotion_id'] !== '') {
            $query->whereHas('coupon', function ($q) use ($filter) {
                $q->where('promotion_id', $filter['promotion_id']);
            });
        }

        if (isset($filter['promotion_date'][0]) && $filter['promotion_date'][0] !== '') {
            $startDate = $filter['promotion_date'][0];
            $endDate = $filter['promotion_date'][1] ?? null;

            if ($endDate) {
                $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($startDate)));
                $query->whereDate('created_at', '<=', date('Y-m-d', strtotime($endDate)));
            } else {
                $query->whereDate('created_at', date('Y-m-d', strtotime($startDate)));
            }
        }

        return $datatable->eloquent($query)
            ->addIndexColumn()
            ->addColumn('promotion_name', function ($data) {
                return optional(optional($data->coupon)->promotion)->name ?? '-';
            })
            ->addColumn('coupon_code', function ($data) {
                return optional($data->coupon)->coupon_code ?? $data->coupon_code ?? '-';
            })
            ->addColumn('customer_name', function ($data) {
                $user = $data->user;
                $Profile_image = optional($user)->profile_image ?? default_user_avatar();
                $name = optional($user)->full_name ?? default_user_name();
                $phone = optional($user)->mobile ?? '--';
                return '
                    <div class="d-flex align-items-center text-decoration-none" style="color:#c39b61;">
                        <div class="me-3">
                            <img src="' . $Profile_image . '" class="avatar avatar-md rounded-circle" alt="' . $name . '" width="40" height="40">
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fw-bold">' . $name . '</span>
                            <small class="text-muted">' . $phone . '</small>
                        </div>
                    </div>
                ';
            })
            ->addColumn('booking_id', function ($data) {
                if (! $data->booking_id) {
                    return '-';
                }
                $url = url('app/bookings?booking_id=' . $data->booking_id);
                return '<a href="' . $url . '">' . $data->booking_id . '</a>';
            })
            ->addColumn('discount_amount', function ($data) {
                return Currency::format($data->discount ?? 0);
            })
            ->editColumn('created_at', function ($data) {
                return customDate($data->created_at);
            })
            ->rawColumns(['customer_name', 'booking_id'])
            ->toJson();
    }

    public function order_report(Request $request)
    {
        $module_title = 'order_report.title';

        $module_name = '.order-report';
        $export_import = true;
        $export_columns = [
            [
                'value' => 'order_code',
                'text' => 'Order Code',
            ],
            [
                'value' => 'customer_name',
                'text' => 'Customer Name',
            ],
            [
                'value' => 'placed_on',
                'text' => 'placed On',
            ],
            [
                'value' => 'items',
                'text' => 'Items',
            ],
            [
                'value' => 'total_admin_earnings',
                'text' => 'Total Amount',
            ]

        ];
        $export_url = route('backend.reports.order_booking_report_review');


        return view('backend.reports.order-report', compact('module_title', 'module_name', 'export_import', 'export_columns', 'export_url'));
    }


    public function order_report_index_data(DataTables $datatable, Request $request)
    {
        $bookings = Booking::with('booking_service.employee', 'booking_service.service', 'user', 'bookingTransaction');


        $filter = $request->filter;

        if (isset($filter)) {
            if (isset($filter['payment_status']) && $filter['payment_status'] !== '') {
                if ((int) $filter['payment_status'] === 1) {
                    $bookings->paid();
                } else {
                    $bookings->unpaid();
                }
            }

            if (isset($filter['order_date'][0])) {
                $startDate = $filter['order_date'][0];
                $endDate = $filter['order_date'][1] ?? null;

                if ($endDate) {
                    $bookings->whereDate('created_at', '>=', date('Y-m-d', strtotime($startDate)));
                    $bookings->whereDate('created_at', '<=', date('Y-m-d', strtotime($endDate)));
                } else {
                    $bookings->whereDate('created_at', date('Y-m-d', strtotime($startDate)));
                }
            }
        }

        return $datatable->eloquent($bookings)
            ->addIndexColumn()
            ->editColumn('order_code', function ($data) {
                return setting('inv_prefix') . optional($data->orderGroup)->order_code;
            })
            ->editColumn('customer_name', function ($data) {
                $Profile_image = optional($data->user)->profile_image ?? default_user_avatar();
                $name = optional($data->user)->full_name ?? default_user_name();
                $mobile = optional($data->user)->mobile ?? '--';
                return view('booking::backend.bookings.datatable.user_id', compact('Profile_image', 'name', 'mobile'));
            })
            ->addColumn('phone', function ($data) {
                return optional($data->user)->mobile ?? '-';
            })
            ->editColumn('placed_on', function ($data) {
                return customDate($data->created_at);
            })
            ->editColumn('items', function ($data) {
                return $data->booking_service->pluck('service.name')->join(', ');
            })
            ->editColumn('payment', function ($data) {
                return $data->bookingTransaction ? __('order_report.paid') : __('order_report.unpaid');
            })
            ->editColumn('status', function ($data) {
                return $data->booking_service->pluck('discount_amount')->join(', ');
            })
            ->editColumn('total_admin_earnings', function ($data) {
                $totalPrice = $data->booking_service->sum('service_price');
                $totalDiscount = $data->booking_service->sum('discount_amount');
                $netTotal = $totalPrice - $totalDiscount;

                return Currency::format($netTotal);
            })
            ->filterColumn('customer_name', function ($query, $keyword) {
                $query->whereHas('user', function ($q) use ($keyword) {
                    $q->where('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('mobile', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('phone', function ($query, $keyword) {
                $query->whereHas('user', function ($q) use ($keyword) {
                    $q->where('mobile', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('updated_at', function ($data) {
                $diff = Carbon::now()->diffInHours($data->updated_at);
                return $diff < 25 ? $data->updated_at->diffForHumans() : $data->updated_at->isoFormat('llll');
            })
            ->orderColumns(['id'], '-:column $1')
            ->rawColumns(['phone', 'customer_name', 'payment', 'status'])
            ->toJson();
    }

    public function daily_booking_report_index_data(Datatables $datatable, Request $request)
    {
        $query = Booking::dailyReport();

        $data = $request->all();

        $filter = $request->filter;
        if (isset($filter['booking_date'])) {
            $bookingDates = explode(' to ', $filter['booking_date']);

            if (count($bookingDates) >= 2) {
                $startDate = date('Y-m-d 00:00:00', strtotime($bookingDates[0]));
                $endDate = date('Y-m-d 23:59:59', strtotime($bookingDates[1]));

                $query->where('bookings.start_date_time', '>=', $startDate)
                    ->where('bookings.start_date_time', '<=', $endDate);
            } elseif (count($bookingDates) === 1) {
                $singleDate = date('Y-m-d', strtotime($bookingDates[0]));
                $startDate = $singleDate . ' 00:00:00';
                $endDate = $singleDate . ' 23:59:59';
                $query->whereBetween('bookings.start_date_time', [$startDate, $endDate]);
            }
        }

        return $datatable->eloquent($query)
            ->editColumn('start_date_time', function ($data) {
                return customDate($data->start_date_time);
            })
            ->editColumn('total_booking', function ($data) {
                return $data->total_booking;
            })
            ->editColumn('total_service', function ($data) {
                return $data->total_service ?? 0;
            })
            ->editColumn('total_service_amount', function ($data) {
                $totalServiceAmount = Booking::totalservice($data->total_tax_amount ?? 0)
                    ->whereDate('bookings.start_date_time', '=', $data->start_date_time)
                    ->first();

                return Currency::format($totalServiceAmount->total_service_amount ?? 0);
            })
            ->editColumn('total_tax_amount', function ($data) {
                return Currency::format($data->total_tax_amount ?? 0);
            })
            ->editColumn('total_amount', function ($data) {
                $totalServiceAmount = Booking::totalservice($data->total_tax_amount ?? 0)
                    ->whereDate('bookings.start_date_time', '=', $data->start_date_time)
                    ->first();

                return Currency::format($totalServiceAmount->total_amount ?? 0);
            })
            ->addIndexColumn()
            ->rawColumns([])
            ->toJson();
    }


    public function overall_booking_report(Request $request)
    {
        $module_title = __('report.title_overall_report');

        $module_name = 'overall-booking-report';
        $export_import = true;
        $export_columns = [
            [
                'value' => 'date',
                'text' => 'Date',
            ],
            [
                'value' => 'inv_id',
                'text' => 'Inv ID',
            ],
            [
                'value' => 'employee',
                'text' => 'Staff',
            ],
            [
                'value' => 'total_service',
                'text' => 'Total Service',
            ],
            [
                'value' => 'total_service_amount',
                'text' => 'Total Service Amount',
            ],
            [
                'value' => 'total_tax_amount',
                'text' => 'Taxes',
            ],
            [
                'value' => 'total_amount',
                'text' => 'Final Amount',
            ],
        ];
        $export_url = route('backend.reports.overall-booking-report-review');

        return view('backend.reports.overall-booking-report', compact('module_title', 'module_name', 'export_import', 'export_columns', 'export_url'));
    }

    public function overall_booking_report_index_data(Datatables $datatable, Request $request)
    {
        $query = Booking::overallReport();

        if ($request->has('booing_id')) {
            $query->where('bookings.id', $request->booing_id);
        }

        if ($request->has('date_range')) {
            $dateRange = explode(' to ', $request->date_range);
            if (isset($dateRange[1])) {
                $startDate = $dateRange[0] ?? date('Y-m-d');
                $endDate = $dateRange[1] ?? date('Y-m-d');
                $query->whereDate('start_date_time', '>=', $startDate)
                    ->whereDate('start_date_time', '<=', $endDate);
            }
        }

        $filter = $request->filter;

        $filter = $request->filter;
        if (isset($filter['booking_date'])) {
            $bookingDates = explode(' to ', $filter['booking_date']);

            if (count($bookingDates) >= 2) {
                $startDate = date('Y-m-d 00:00:00', strtotime($bookingDates[0]));
                $endDate = date('Y-m-d 23:59:59', strtotime($bookingDates[1]));

                $query->where('bookings.start_date_time', '>=', $startDate)
                    ->where('bookings.start_date_time', '<=', $endDate);
            } elseif (count($bookingDates) === 1) {
                $singleDate = date('Y-m-d', strtotime($bookingDates[0]));
                $startDate = $singleDate . ' 00:00:00';
                $endDate = $singleDate . ' 23:59:59';
                $query->whereBetween('bookings.start_date_time', [$startDate, $endDate]);
            }
        }

        if (isset($filter['employee_id'])) {
            $query->whereHas('services', function ($q) use ($filter) {
                $q->where('employee_id', $filter['employee_id']);
            });
        }




        return $datatable->eloquent($query)
            ->editColumn('start_date_time', function ($data) {
                return customDate($data->start_date_time);
            })
            ->editColumn('id', function ($data) {
                return setting('booking_invoice_prifix') . $data->id;
            })
            ->editColumn('employee_id', function ($data) {
                // return $data->services->first()->employee?->full_name ?? '-';
                $employee = optional($data->services->first())->employee;
                $Profile_image = $employee->profile_image ?? default_user_avatar();
                $name = $employee->full_name ?? default_user_name();
                $mobile = $employee->mobile ?? '--';

                return view('booking::backend.bookings.datatable.employee_id', compact('Profile_image', 'name', 'mobile'));
            })
            ->editColumn('total_service', function ($data) {
                return $data->total_service;
            })
            ->editColumn('total_service_amount', function ($data) {
                return Currency::format($data->total_service_amount ?? 0);
            })
            ->editColumn('total_tax_amount', function ($data) {
                return Currency::format($data->total_tax_amount ?? 0);
            })
            ->editColumn('total_amount', function ($data) {
                return Currency::format($data->total_amount);
            })
            ->orderColumn('employee_id', function ($query, $order) {
                $query->orderBy(new Expression('(SELECT employee_id FROM booking_services WHERE booking_id = bookings.id LIMIT 1)'), $order);
            }, 1)
            ->addIndexColumn()
            ->rawColumns([])
            ->toJson();
    }


    public function payout_report(Request $request)
    {
        $module_title = __('report.title_staff_report');

        $module_name = 'payout-report-review';
        $export_import = true;
        $export_columns = [
            [
                'value' => 'date',
                'text' => 'Payment Date',
            ],
            [
                'value' => 'employee',
                'text' => 'Staff',
            ],
            [
                'value' => 'commission_amount',
                'text' => 'Commission Amount',
            ],
            [
                'value' => 'payment_type',
                'text' => 'Payment Type',
            ],
            [
                'value' => 'total_pay',
                'text' => 'Total Pay',
            ],
        ];
        $export_url = route('backend.reports.payout-report-review');

        return view('backend.reports.payout-report', compact('module_title', 'module_name', 'export_import', 'export_columns', 'export_url'));
    }

    public function payout_report_index_data(Datatables $datatable, Request $request)
    {
        $query = EmployeeEarning::with('employee');

        $filter = $request->filter;

        if (isset($filter['booking_date'])) {
            $bookingDates = explode(' to ', $filter['booking_date']);

            if (count($bookingDates) >= 2) {
                $startDate = date('Y-m-d 00:00:00', strtotime($bookingDates[0]));
                $endDate = date('Y-m-d 23:59:59', strtotime($bookingDates[1]));

                $query->where('payment_date', '>=', $startDate)
                    ->where('payment_date', '<=', $endDate);
            } elseif (count($bookingDates) === 1) {
                $singleDate = date('Y-m-d', strtotime($bookingDates[0]));
                $startDate = $singleDate . ' 00:00:00';
                $endDate = $singleDate . ' 23:59:59';
                $query->whereBetween('payment_date', [$startDate, $endDate]);
            }
        }

        if (isset($filter['employee_id'])) {
            $query->whereHas('employee', function ($q) use ($filter) {
                $q->where('employee_id', $filter['employee_id']);
            });
        }

        return $datatable->eloquent($query)
            ->editColumn('payment_date', function ($data) {
                return customDate($data->payment_date ?? '-');
            })
            ->editColumn('first_name', function ($data) {
                $Profile_image = optional($data->employee)->profile_image ?? default_user_avatar();
                $name = optional($data->employee)->full_name ?? default_user_name();
                $mobile = optional($data->employee)->mobile ?? '--';
                return view('booking::backend.bookings.datatable.employee_id', compact('Profile_image', 'name', 'mobile'));
            })
            ->editColumn('commission_amount', function ($data) {
                return Currency::format($data->commission_amount ?? 0);
            })
            ->editColumn('total_pay', function ($data) {
                return Currency::format($data->total_amount ?? 0);
            })
            ->editColumn('updated_at', function ($data) {
                $module_name = $this->module_name;

                $diff = Carbon::now()->diffInHours($data->updated_at);

                if ($diff < 25) {
                    return $data->updated_at->diffForHumans();
                } else {
                    return $data->updated_at->isoFormat('llll');
                }
            })
            // ->orderColumn('first_name', function ($query, $order) {
            //     $query->orderBy(new Expression('(SELECT id FROM users WHERE id = employee_id LIMIT 1)'), $order);
            // }, 1)
            ->orderColumn('first_name', function ($query, $direction) {
                $query->leftJoin('users', 'users.id', '=', 'employee_id')
                    ->orderBy('users.first_name', $direction)
                    ->orderBy('users.last_name', $direction);
            })

            ->orderColumn('total_pay', function ($query, $order) {
                $query->orderBy(new Expression('(SELECT total_amount FROM users WHERE id = employee_id LIMIT 1)'), $order);
            }, 1)

            ->addIndexColumn()
            ->rawColumns([])
            ->orderColumns(['id'], '-:column $1')
            ->toJson();
    }

    public function staff_report(Request $request)
    {
        $module_title = __('report.title_staff_service_report');

        $module_name = 'staff-report-review';
        $export_import = true;
        $export_columns = [
            [
                'value' => 'employee',
                'text' => 'Staff',
            ],
            [
                'value' => 'total_services',
                'text' => 'Total Services',
            ],
            [
                'value' => 'total_service_amount',
                'text' => 'Total Amount',
            ],
            [
                'value' => 'total_commission_earn',
                'text' => 'Commission Earn',
            ],
            [
                'value' => 'total_earning',
                'text' => 'Total Earning',
            ],
        ];
        $export_url = route('backend.reports.staff-report-review');

        return view('backend.reports.staff-report', compact('module_title', 'module_name', 'export_import', 'export_columns', 'export_url'));
    }

    public function payment_transactions_report(Request $request)
    {
        $module_title = __('report.title_payment_transactions_report');
        $module_name = 'payment-transactions-report';

        $payment_methods = collect();

        if (Schema::hasColumn('invoices', 'payment_method')) {
            $payment_methods = Invoice::query()
                ->select('payment_method')
                ->whereNotNull('payment_method')
                ->distinct()
                ->pluck('payment_method');
        }

        $payment_methods = $payment_methods
            ->merge(
                BookingTransaction::query()
                    ->select('transaction_type')
                    ->whereNotNull('transaction_type')
                    ->distinct()
                    ->pluck('transaction_type')
            )
            ->merge(
                OrderGroup::query()
                    ->select('payment_method')
                    ->where('payment_status', 'paid')
                    ->whereNotNull('payment_method')
                    ->distinct()
                    ->pluck('payment_method')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('backend.reports.payment-transactions-report', compact('module_title', 'module_name', 'payment_methods'));
    }

    public function payment_transactions_report_index_data(Datatables $datatable, Request $request)
    {
        $filter = $request->filter;
        [$startDate, $endDate] = $this->parseDateRange($filter['payment_date'] ?? []);
        $selectedPaymentMethod = $filter['payment_method'] ?? '';

        $invoiceQuery = Invoice::with('user');
        if ($startDate) {
            $invoiceQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $invoiceQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $invoiceRows = $invoiceQuery->get()->map(function (Invoice $invoice) {
            $paymentMethod = $this->resolveInvoicePaymentMethod($invoice);

            return [
                'created_at_raw' => optional($invoice->created_at)->timestamp ?? 0,
                'created_at' => customDate($invoice->created_at),
                'invoice_id' => $this->formatInvoiceLink($invoice),
                'customer_name' => $this->formatCustomerCell($invoice->user),
                'transaction_id' => $this->resolveInvoiceTransactionId($invoice),
                'payment_method_raw' => $paymentMethod,
                'payment_method' => $this->formatPaymentMethod($paymentMethod),
                'payment_status' => __('messages.paid'),
                'service_amount' => Currency::format($this->resolveInvoiceSubtotalAmount($invoice)),
                'discount_amount' => Currency::format((float) ($invoice->discount_amount ?? 0)),
                'total_amount' => Currency::format((float) ($invoice->final_total ?? 0)),
            ];
        });

        $bookingQuery = BookingTransaction::with(['booking.user', 'booking.services']);
        if ($selectedPaymentMethod !== '') {
            $bookingQuery->where('transaction_type', $selectedPaymentMethod);
        }
        if ($startDate) {
            $bookingQuery->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $bookingQuery->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $resolvedInvoiceByBooking = [];
        $standaloneBookingRows = $bookingQuery->get()
            ->filter(function (BookingTransaction $transaction) use (&$resolvedInvoiceByBooking) {
                if ($transaction->invoice_id) {
                    return false;
                }

                $bookingId = (int) ($transaction->booking_id ?? 0);
                if (! array_key_exists($bookingId, $resolvedInvoiceByBooking)) {
                    $resolvedInvoiceByBooking[$bookingId] = optional($this->resolveBookingTransactionInvoice($transaction))->id;
                }

                return $resolvedInvoiceByBooking[$bookingId] === null;
            })
            ->map(function (BookingTransaction $data) {
                $services = optional($data->booking)->services;
                $serviceAmount = $services ? $services->sum('service_price') : 0;
                $discount = (float) ($data->discount_amount ?? 0);

                return [
                    'created_at_raw' => optional($data->created_at)->timestamp ?? 0,
                    'created_at' => customDate($data->created_at),
                    'invoice_id' => '-',
                    'customer_name' => $this->formatCustomerCell(optional($data->booking)->user),
                    'transaction_id' => $data->external_transaction_id ?? '-',
                    'payment_method_raw' => $data->transaction_type,
                    'payment_method' => $this->formatPaymentMethod($data->transaction_type),
                    'payment_status' => $data->payment_status == 1 ? __('messages.paid') : __('messages.unpaid'),
                    'service_amount' => Currency::format($serviceAmount),
                    'discount_amount' => Currency::format($discount),
                    'total_amount' => Currency::format($serviceAmount - $discount),
                ];
            });

        $rows = $invoiceRows
            ->merge($standaloneBookingRows)
            ->filter(function (array $row) use ($selectedPaymentMethod) {
                if ($selectedPaymentMethod === '') {
                    return true;
                }

                return ($row['payment_method_raw'] ?? null) === $selectedPaymentMethod;
            })
            ->sortByDesc('created_at_raw')
            ->values();

        return $datatable->collection($rows)
            ->addIndexColumn()
            ->rawColumns(['customer_name', 'invoice_id'])
            ->toJson();
    }

    private function formatCustomerCell($user): string
    {
        $profileImage = optional($user)->profile_image ?? default_user_avatar();
        $name = optional($user)->full_name ?? default_user_name();
        $phone = optional($user)->mobile ?? '--';

        return '
            <div class="d-flex align-items-center text-decoration-none" style="color:#c39b61;">
                <div class="me-3">
                    <img src="' . $profileImage . '" class="avatar avatar-md rounded-circle" alt="' . $name . '" width="40" height="40">
                </div>
                <div class="d-flex flex-column">
                    <span class="fw-bold">' . $name . '</span>
                    <small class="text-muted">' . $phone . '</small>
                </div>
            </div>
        ';
    }

    private function formatInvoiceLink(Invoice $invoice): string
    {
        $url = route('app.invoice', ['invoice_id' => $invoice->id]) . '#invoice-card-' . $invoice->id;

        return '<a href="' . $url . '">INV-' . $invoice->id . '</a>';
    }

    private function formatPaymentMethod(?string $paymentMethod): string
    {
        return $paymentMethod ? ucwords(str_replace('_', ' ', $paymentMethod)) : '-';
    }

    private function resolveInvoicePaymentMethod(Invoice $invoice): ?string
    {
        if ($invoice->payment_method) {
            return $invoice->payment_method;
        }

        $bookingTransaction = null;

        if (Schema::hasColumn('booking_transactions', 'invoice_id')) {
            $bookingTransaction = BookingTransaction::query()
                ->where('invoice_id', $invoice->id)
                ->whereNotNull('transaction_type')
                ->latest('id')
                ->first();
        }

        if ($bookingTransaction?->transaction_type) {
            return $bookingTransaction->transaction_type;
        }

        if (! empty($invoice->product_ids)) {
            $orderGroup = OrderGroup::query()
                ->whereIn('id', (array) $invoice->product_ids)
                ->whereNotNull('payment_method')
                ->latest('id')
                ->first();

            if ($orderGroup?->payment_method) {
                return $orderGroup->payment_method;
            }
        }

        if (! empty($invoice->cart_ids)) {
            $fallbackTransaction = BookingTransaction::query()
                ->whereIn('booking_id', (array) $invoice->cart_ids)
                ->whereNotNull('transaction_type')
                ->latest('id')
                ->first();

            if ($fallbackTransaction?->transaction_type) {
                return $fallbackTransaction->transaction_type;
            }
        }

        return null;
    }

    private function resolveInvoiceTransactionId(Invoice $invoice): string
    {
        $bookingTransaction = null;

        if (Schema::hasColumn('booking_transactions', 'invoice_id')) {
            $bookingTransaction = BookingTransaction::query()
                ->where('invoice_id', $invoice->id)
                ->whereNotNull('external_transaction_id')
                ->latest('id')
                ->first();
        }

        if ($bookingTransaction?->external_transaction_id) {
            return $bookingTransaction->external_transaction_id;
        }

        if (! empty($invoice->cart_ids)) {
            $fallbackTransaction = BookingTransaction::query()
                ->whereIn('booking_id', (array) $invoice->cart_ids)
                ->whereNotNull('external_transaction_id')
                ->latest('id')
                ->first();

            if ($fallbackTransaction?->external_transaction_id) {
                return $fallbackTransaction->external_transaction_id;
            }
        }

        return '-';
    }

    private function resolveInvoiceSubtotalAmount(Invoice $invoice): float
    {
        return (float) ($invoice->final_total ?? 0)
            + (float) ($invoice->discount_amount ?? 0)
            + (float) ($invoice->loyalty_points_discount ?? 0)
            + (float) ($invoice->gift_amount ?? 0);
    }

    private function resolveBookingTransactionInvoice(BookingTransaction $transaction): ?Invoice
    {
        if ($transaction->invoice_id) {
            $invoice = Invoice::query()->find($transaction->invoice_id);

            if ($invoice) {
                return $invoice;
            }
        }

        if (! $transaction->booking_id) {
            return null;
        }

        return Invoice::query()
            ->where(function ($query) use ($transaction) {
                $query->whereJsonContains('cart_ids', (int) $transaction->booking_id)
                    ->orWhereJsonContains('cart_ids', (string) $transaction->booking_id);
            })
            ->latest('id')
            ->first();
    }


    public function staff_report_index_data(Datatables $datatable, Request $request)
    {
        $query = User::staffReport();

        $filter = $request->filter;

        if (isset($filter['employee_id'])) {
            $query->where('id', $filter['employee_id']);
        }

        return $datatable->eloquent($query)
            // ->editColumn('first_name', function ($data) {
            //     return $data->full_name;
            // })
            ->editColumn('first_name', function ($data) {
                $Profile_image = optional($data)->profile_image ?? default_user_avatar();
                $name = optional($data)->full_name ?? default_user_name();
                $mobile = optional($data)->mobile ?? '--';
                return view('booking::backend.bookings.datatable.employee_id', compact('Profile_image', 'name', 'mobile'));
            })
            ->orderColumn('first_name', function ($query, $order) {
                $query->orderBy('users.first_name', $order) // Ordering by first name
                    ->orderBy('users.last_name', $order); // Ordering by first name
            }, 1)
            ->editColumn('total_services', function ($data) {
                return $data->employee_booking_count ?? 0;
            })
            ->editColumn('total_service_amount', function ($data) {
                return Currency::format($data->employee_booking_sum_service_price ?? 0);
            })
            ->editColumn('total_commission_earn', function ($data) {
                return Currency::format($data->commission_earning_sum_commission_amount ?? 0);
            })
            ->editColumn('total_earning', function ($data) {
                return Currency::format($data->commission_earning_sum_commission_amount ?? 0);
            })
            ->editColumn('updated_at', function ($data) {
                $module_name = $this->module_name;

                $diff = Carbon::now()->diffInHours($data->updated_at);

                if ($diff < 25) {
                    return $data->updated_at->diffForHumans();
                } else {
                    return $data->updated_at->isoFormat('llll');
                }
            })
            ->orderColumn('total_services', function ($data, $order) {
                $data->selectRaw('(SELECT COUNT(service_id) FROM booking_services WHERE employee_id = users.id) as total_services')
                    ->orderBy('total_services', $order);
            })

            ->orderColumn('total_service_amount', function ($data, $order) {
                $data->selectRaw('(SELECT SUM(service_price) FROM booking_services WHERE employee_id = users.id) as total_service_amount')
                    ->orderBy('total_service_amount', $order);
            })

            ->orderColumn('total_service_amount', function ($data, $order) {
                $data->selectRaw('(SELECT SUM(service_price) FROM booking_services WHERE employee_id = users.id) as total_service_amount')
                    ->orderBy('total_service_amount', $order);
            })

            ->orderColumn('total_commission_earn', function ($data, $order) {
                $data->selectRaw('(SELECT SUM(commission_amount) FROM commission_earnings WHERE employee_id = users.id) as total_commission_earn')
                    ->orderBy('total_commission_earn', $order);
            })

            ->orderColumn('total_earning', function ($data, $order) {
                $data->selectRaw('(SELECT SUM(service_price) FROM booking_services WHERE employee_id = users.id) as total_earning')
                    ->orderBy('total_earning', $order);
            })

            ->addIndexColumn()
            ->rawColumns([])
            ->orderColumns(['id'], '-:column $1')
            ->toJson();
    }

    public function daily_booking_report_review(Request $request)
    {
        $this->exportClass = '\App\Exports\DailyReportsExport';

        return $this->export($request);
    }

    public function overall_booking_report_review(Request $request)
    {
        $this->exportClass = '\App\Exports\OverallReportsExport';

        return $this->export($request);
    }

    public function payout_report_review(Request $request)
    {
        $this->exportClass = '\App\Exports\StaffPayoutReportExport';

        return $this->export($request);
    }

    public function staff_report_review(Request $request)
    {
        $this->exportClass = '\App\Exports\StaffServiceReportExport';

        return $this->export($request);
    }
    public function order_booking_report_review(Request $request)
    {
        $this->exportClass = '\App\Exports\OrderReportsExport';

        return $this->export($request);
    }
}