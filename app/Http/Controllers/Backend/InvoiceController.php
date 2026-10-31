<?php

namespace App\Http\Controllers\Backend;

use App\Exports\InvoicesExport;
use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Booking\Models\BookingService;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_invoice')->only(['index']);
        $this->middleware('permission:delete_invoice')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $module_action = 'List';
        $module_title  = 'Invoice Cards';

        $query = Invoice::query()->with('user');

        $this->applyFilters($query, $request);

        $invoices = $query->orderBy('created_at', 'desc')->get();
        $invoices->each(function (Invoice $invoice) {
            $invoice->setAttribute('display_total', $this->resolveInvoiceDisplayTotal($invoice));
        });

        return view('backend.invoice.index_datatable', compact('module_action', 'invoices', 'module_title'));
    }

    public function export(Request $request)
    {
        $this->middleware('permission:view_invoice');

        $filters = $request->only([
            'invoice_id',
            'customer_name',
            'mobile',
            'date',
            'date_from',
            'date_to',
            'payment_method',
            'has_coupon',
            'min_total',
            'max_total',
            'min_discount',
            'invoice_type',
            'min_invoices_count',
            'sort_by',
            'sort_dir',
        ]);

        $fileName = 'invoices_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new InvoicesExport($filters), $fileName);
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return redirect()->back()->with('success', __('messages.deleted_successfully'));
    }

    /**
     * Apply all search/filter conditions to the given query.
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        // رقم الفاتورة
        if ($request->filled('invoice_id')) {
            $query->where('id', $request->invoice_id);
        }

        // اسم العميل
        if ($request->filled('customer_name')) {
            $search = '%' . $request->customer_name . '%';
            $query->whereHas('user', function ($q) use ($search) {
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$search]);
            });
        }

        // الجوال
        if ($request->filled('mobile')) {
            $mobile = $request->mobile;
            $mobile = strtr($mobile, [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);

            $candidates = \App\Support\SaudiPhoneNumber::lookupDigits($mobile);
            if (!empty($candidates)) {
                $query->whereHas('user', fn($q) => $q->whereIn('mobile', $candidates));
            } else {
                $query->whereHas('user', function ($q) use ($mobile) {
                    $q->where('mobile', 'like', '%' . $mobile . '%');
                    if (str_starts_with($mobile, '+')) {
                        $q->orWhere('mobile', 'like', '%' . substr($mobile, 1) . '%');
                    }
                    if (str_starts_with($mobile, '05')) {
                        $suffix = substr($mobile, 1);
                        $q->orWhere('mobile', 'like', '%+966' . $suffix . '%')
                          ->orWhere('mobile', 'like', '%966' . $suffix . '%');
                    }
                });
            }
        }

        // تاريخ محدد
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // من تاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        // إلى تاريخ
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // طريقة الدفع
        if ($request->filled('payment_method')) {
            $pm = $request->payment_method;
            $query->where('payment_method', 'like', '%' . $pm . '%');
        }

        // فلتر الكوبون
        if ($request->filled('has_coupon') && $request->has_coupon === '1') {
            $query->whereNotNull('coupon_code')->where('coupon_code', '!=', '');
        }

        // الحد الأدنى للإجمالي
        if ($request->filled('min_total')) {
            $query->where('final_total', '>=', (float) $request->min_total);
        }

        // الحد الأقصى للإجمالي
        if ($request->filled('max_total')) {
            $query->where('final_total', '<=', (float) $request->max_total);
        }

        // الحد الأدنى للخصم
        if ($request->filled('min_discount')) {
            $query->where('discount_amount', '>=', (float) $request->min_discount);
        }

        // نوع الفاتورة
        if ($request->filled('invoice_type')) {
            switch ($request->invoice_type) {
                case 'booking':
                    $query->whereNotNull('cart_ids')
                          ->where('cart_ids', '!=', '[]')
                          ->where('cart_ids', '!=', 'null');
                    break;
                case 'gift':
                    $query->whereNotNull('gift_ids')
                          ->where('gift_ids', '!=', '[]')
                          ->where('gift_ids', '!=', 'null');
                    break;
                case 'product':
                    $query->whereNotNull('product_ids')
                          ->where('product_ids', '!=', '[]')
                          ->where('product_ids', '!=', 'null');
                    break;
            }
        }

        // العملاء الذين لديهم أكثر من عدد معين من الفواتير
        if ($request->filled('min_invoices_count')) {
            $minCount = (int) $request->min_invoices_count;
            $userIds = Invoice::select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) >= ?', [$minCount])
                ->pluck('user_id');
            $query->whereIn('user_id', $userIds);
        }
    }

    private function resolveInvoiceDisplayTotal(Invoice $invoice): float
    {
        $storedTotal     = (float) ($invoice->final_total ?? 0);
        $computedSubtotal = $this->resolveInvoiceLineSubtotal($invoice);

        if ($computedSubtotal <= 0) {
            return $storedTotal;
        }

        $computedTotal = max(
            $computedSubtotal
            + (float) ($invoice->taxs_service ?? 0)
            - (float) ($invoice->discount_amount ?? 0),
            0
        );

        return max($storedTotal, $computedTotal);
    }

    private function resolveInvoiceLineSubtotal(Invoice $invoice): float
    {
        $bookingIds = $this->normalizeIds($invoice->cart_ids ?? []);
        $giftIds    = $this->normalizeIds($invoice->gift_ids ?? []);

        $bookingSubtotal = empty($bookingIds)
            ? 0.0
            : (float) BookingService::query()
                ->whereIn('booking_id', $bookingIds)
                ->get()
                ->sum(fn (BookingService $service) => max(
                    (float) ($service->service_price ?? 0) - (float) ($service->discount_amount ?? 0),
                    0
                ));

        $giftSubtotal = empty($giftIds)
            ? 0.0
            : (float) GiftCard::query()
                ->whereIn('id', $giftIds)
                ->sum('subtotal');

        $productSubtotal = (float) $invoice->productItems
            ->sum(fn ($item) => (float) ($item->total_price ?? (((float) ($item->unit_price ?? 0)) * ((int) ($item->qty ?? 1)))));

        return $bookingSubtotal + $giftSubtotal + $productSubtotal;
    }

    private function normalizeIds(array|string|null $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => $item !== null && $item !== ''));
            }

            return array_values(array_filter(explode(',', $value), fn ($item) => trim((string) $item) !== ''));
        }

        return [];
    }
}
