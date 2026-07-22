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

    public function __construct(array $columns, array $dateRange)
    {
        $this->columns = $columns;
        $this->dateRange = $dateRange;
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
