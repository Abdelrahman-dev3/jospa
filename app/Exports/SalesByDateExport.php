<?php

namespace App\Exports;

use Carbon\Carbon;
use Currency;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Modules\Booking\Models\BookingTransaction;
use Modules\Product\Models\OrderGroup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesByDateExport implements FromCollection, WithHeadings, WithStyles
{
    public array $columns;
    public array $dateRange;
    public array $filters;

    public function __construct(array $columns, array $dateRange, array $filters = [])
    {
        $this->columns = $columns;
        $this->dateRange = $dateRange;
        $this->filters = $filters;
    }

    public function headings(): array
    {
        $modifiedHeadings = [];

        foreach ($this->columns as $column) {
            $modifiedHeadings[] = ucwords(str_replace('_', ' ', $column));
        }

        return $modifiedHeadings;
    }

    public function collection()
    {
        $startDate = $this->dateRange[0] ?? null;
        $endDate = $this->dateRange[1] ?? null;

        $branchId = $this->filters['branch_id'] ?? null;
        $categoryId = $this->filters['category_id'] ?? null;
        $serviceId = $this->filters['service_id'] ?? null;
        $employeeId = $this->filters['employee_id'] ?? null;
        $customerId = $this->filters['customer_id'] ?? null;
        $paymentMethod = $this->filters['payment_method'] ?? null;
        $bookingStatus = $this->filters['booking_status'] ?? null;
        $serviceType = $this->filters['service_type'] ?? null;
        $couponFilter = $this->filters['has_coupon'] ?? null;

        $orders = collect();
        if (! $branchId && ! $employeeId && ! $serviceId && ! $categoryId && (! $serviceType || $serviceType === 'all')) {
            $orderQuery = OrderGroup::query()->with('order.orderItems');
            if ($startDate) {
                $orderQuery->whereDate('created_at', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $orderQuery->whereDate('created_at', '<=', $endDate->toDateString());
            }
            if ($customerId) {
                $orderQuery->where('user_id', $customerId);
            }
            if ($paymentMethod) {
                $orderQuery->where('payment_type', $paymentMethod);
            }
            if ($couponFilter === 'yes') {
                $orderQuery->where(function ($q) {
                    $q->where('total_coupon_discount_amount', '>', 0)
                        ->orWhere('total_discount_amount', '>', 0);
                });
            } elseif ($couponFilter === 'no') {
                $orderQuery->where(function ($q) {
                    $q->whereNull('total_coupon_discount_amount')->orWhere('total_coupon_discount_amount', 0);
                })->where(function ($q) {
                    $q->whereNull('total_discount_amount')->orWhere('total_discount_amount', 0);
                });
            }
            $orders = $orderQuery->get();
        }

        $bookings = collect();
        if (! $serviceType || in_array($serviceType, ['all', 'salon', 'home', 'package'])) {
            $bookingQuery = Booking::query()
                ->with([
                    'bookingService.service',
                    'services',
                    'products',
                    'bookingPackages',
                    'bookingTransaction',
                    'transactions',
                    'userCouponRedeem',
                ]);

            if ($bookingStatus) {
                $bookingQuery->where('status', $bookingStatus);
            } else {
                $bookingQuery->where(function ($q) {
                    $q->where('status', 'completed')
                        ->orWhere('payment_status', 1)
                        ->orWhere('payment_status', '1')
                        ->orWhere('payment_status', 'paid')
                        ->orWhereHas('transactions', function ($tq) {
                            $tq->whereIn('payment_status', [1, '1', 'paid']);
                        });
                });
            }

            if ($startDate) {
                $bookingQuery->where(function ($q) use ($startDate) {
                    $q->whereDate('start_date_time', '>=', $startDate->toDateString())
                        ->orWhere(function ($sub) use ($startDate) {
                            $sub->whereNull('start_date_time')
                                ->whereDate('created_at', '>=', $startDate->toDateString());
                        });
                });
            }
            if ($endDate) {
                $bookingQuery->where(function ($q) use ($endDate) {
                    $q->whereDate('start_date_time', '<=', $endDate->toDateString())
                        ->orWhere(function ($sub) use ($endDate) {
                            $sub->whereNull('start_date_time')
                                ->whereDate('created_at', '<=', $endDate->toDateString());
                        });
                });
            }

            if ($branchId) {
                $bookingQuery->where('branch_id', $branchId);
            }

            if ($customerId) {
                $bookingQuery->where('user_id', $customerId);
            }

            if ($employeeId) {
                $bookingQuery->whereHas('bookingService', function ($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId);
                });
            }

            if ($serviceId) {
                $bookingQuery->whereHas('bookingService', function ($q) use ($serviceId) {
                    $q->where('service_id', $serviceId);
                });
            }

            if ($categoryId) {
                $bookingQuery->whereHas('bookingService.service', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            }

            if ($paymentMethod) {
                $bookingQuery->where(function ($q) use ($paymentMethod) {
                    $q->where('payment_type', $paymentMethod)
                        ->orWhereHas('transactions', function ($tq) use ($paymentMethod) {
                            $tq->where('transaction_type', $paymentMethod);
                        });
                });
            }

            if ($serviceType === 'salon') {
                $bookingQuery->where(function ($q) {
                    $q->where('booking_type', 'salon')->orWhereNull('booking_type');
                });
            } elseif ($serviceType === 'home') {
                $bookingQuery->where('booking_type', 'home');
            } elseif ($serviceType === 'package') {
                $bookingQuery->whereHas('bookingPackages');
            }

            if ($couponFilter === 'yes') {
                $bookingQuery->where(function ($q) {
                    $q->whereHas('userCouponRedeem')
                        ->orWhereHas('bookingService', fn ($s) => $s->whereNotNull('coupon_code')->orWhere('discount_amount', '>', 0))
                        ->orWhereHas('transactions', fn ($t) => $t->where('discount_amount', '>', 0));
                });
            } elseif ($couponFilter === 'no') {
                $bookingQuery->whereDoesntHave('userCouponRedeem')
                    ->where(function ($q) {
                        $q->whereDoesntHave('bookingService', function ($s) {
                            $s->whereNotNull('coupon_code')->orWhere('discount_amount', '>', 0);
                        });
                    });
            }

            $bookings = $bookingQuery->get();
        }

        $giftCards = collect();
        if (! $branchId && ! $employeeId && ! $categoryId && (! $serviceType || in_array($serviceType, ['all', 'gift_card']))) {
            $giftQuery = GiftCard::query()->where('payment_status', 1);
            if ($startDate) {
                $giftQuery->whereDate('created_at', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $giftQuery->whereDate('created_at', '<=', $endDate->toDateString());
            }
            if ($customerId) {
                $giftQuery->where('user_id', $customerId);
            }
            if ($serviceId) {
                $giftQuery->whereJsonContains('requested_services', (string) $serviceId);
            }
            if ($paymentMethod) {
                $giftQuery->where('payment_type', $paymentMethod);
            }
            if ($couponFilter === 'yes') {
                $giftQuery->whereNotNull('coupons');
            } elseif ($couponFilter === 'no') {
                $giftQuery->whereNull('coupons');
            }
            $giftCards = $giftQuery->get();
        }

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
                ];
            }

            $itemsQty = $og->order && $og->order->orderItems ? $og->order->orderItems->sum('qty') : 0;
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

        foreach ($bookings as $booking) {
            $dateObj = $booking->start_date_time ?? $booking->created_at;
            $dateStr = $dateObj ? Carbon::parse($dateObj)->format('Y-m-d') : null;
            if (! $dateStr) {
                continue;
            }

            if (! isset($periodMap[$dateStr])) {
                $periodMap[$dateStr] = [
                    'date' => $dateStr,
                    'orders_count' => 0,
                    'items_count' => 0,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'shipping_cost' => 0.0,
                    'coupons_value' => 0.0,
                ];
            }

            $services = ($booking->bookingService && $booking->bookingService->count() > 0)
                ? $booking->bookingService
                : $booking->services;

            if ($serviceId) {
                $services = $services ? $services->where('service_id', $serviceId) : null;
            }
            if ($employeeId) {
                $services = $services ? $services->where('employee_id', $employeeId) : null;
            }
            if ($categoryId) {
                $services = $services ? $services->filter(fn ($s) => optional($s->service)->category_id == $categoryId) : null;
            }

            $serviceAmt = $services ? (float) $services->sum('service_price') : 0.0;
            $productAmt = (! $serviceId && ! $categoryId && ! $employeeId && $booking->products) ? (float) $booking->products->sum(function ($p) {
                $price = $p->discounted_price && $p->discounted_price > 0 ? $p->discounted_price : $p->product_price;
                return $price * ($p->product_qty ?? 1);
            }) : 0.0;
            $pkgAmt = (! $serviceId && ! $categoryId && ! $employeeId && $booking->bookingPackages) ? (float) $booking->bookingPackages->sum('package_price') : 0.0;

            $itemsCount = ($services ? $services->count() : 0)
                + ((! $serviceId && ! $categoryId && ! $employeeId && $booking->products) ? $booking->products->sum('product_qty') : 0)
                + ((! $serviceId && ! $categoryId && ! $employeeId && $booking->bookingPackages) ? $booking->bookingPackages->count() : 0);

            $couponDiscount = 0.0;
            if ($booking->userCouponRedeem && $booking->userCouponRedeem->discount > 0) {
                $couponDiscount = (float) $booking->userCouponRedeem->discount;
            } elseif ($services && $services->sum('discount_amount') > 0) {
                $couponDiscount = (float) $services->sum('discount_amount');
            } elseif ($booking->bookingTransaction && $booking->bookingTransaction->discount_amount > 0) {
                $couponDiscount = (float) $booking->bookingTransaction->discount_amount;
            } elseif ($booking->transactions && $booking->transactions->sum('discount_amount') > 0) {
                $couponDiscount = (float) $booking->transactions->sum('discount_amount');
            }

            $gross = $serviceAmt + $productAmt + $pkgAmt;
            $net = max(0, $gross - $couponDiscount);

            $periodMap[$dateStr]['orders_count'] += 1;
            $periodMap[$dateStr]['items_count'] += $itemsCount;
            $periodMap[$dateStr]['gross_sales'] += $gross;
            $periodMap[$dateStr]['net_sales'] += $net;
            $periodMap[$dateStr]['coupons_value'] += $couponDiscount;
        }

        foreach ($giftCards as $gift) {
            $dateStr = optional($gift->created_at)->format('Y-m-d');
            if (! $dateStr) {
                continue;
            }

            if (! isset($periodMap[$dateStr])) {
                $periodMap[$dateStr] = [
                    'date' => $dateStr,
                    'orders_count' => 0,
                    'items_count' => 0,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'shipping_cost' => 0.0,
                    'coupons_value' => 0.0,
                ];
            }

            $servicesList = is_array($gift->requested_services)
                ? $gift->requested_services
                : json_decode($gift->requested_services ?? '[]', true);
            $giftItemsCount = is_array($servicesList) && count($servicesList) > 0 ? count($servicesList) : 1;

            $giftShipping = (float) ($gift->options_amount ?? 0);
            $giftSubtotal = (float) ($gift->subtotal ?? 0);
            if ($giftSubtotal <= 0) {
                $giftSubtotal = (float) ($gift->balance ?? 0);
            }

            $giftCoupons = is_array($gift->coupons) ? $gift->coupons : (json_decode($gift->coupons ?? '[]', true) ?? []);
            $giftDiscount = 0.0;
            if (is_array($giftCoupons)) {
                foreach ($giftCoupons as $gc) {
                    if (is_array($gc) && isset($gc['price'])) {
                        $giftDiscount += (float) $gc['price'];
                    } elseif (is_numeric($gc)) {
                        $giftDiscount += (float) $gc;
                    }
                }
            }

            $giftGross = $giftSubtotal + $giftShipping;
            $giftNet = max(0, $giftGross - $giftDiscount);

            $periodMap[$dateStr]['orders_count'] += 1;
            $periodMap[$dateStr]['items_count'] += $giftItemsCount;
            $periodMap[$dateStr]['gross_sales'] += $giftGross;
            $periodMap[$dateStr]['net_sales'] += $giftNet;
            $periodMap[$dateStr]['shipping_cost'] += $giftShipping;
            $periodMap[$dateStr]['coupons_value'] += $giftDiscount;
        }

        ksort($periodMap);

        return collect(array_values($periodMap))->map(function ($row) {
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
    }

    public function styles(Worksheet $sheet)
    {
        if (function_exists('applyExcelStyles')) {
            applyExcelStyles($sheet);
        }
    }
}
