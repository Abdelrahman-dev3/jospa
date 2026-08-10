<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Invoice;
use Illuminate\Http\Request;
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
        $module_title = 'Invoice Cards';

        $query = Invoice::query()->with('user');

        if ($request->filled('invoice_id')) {
            $query->where('id', $request->invoice_id);
        }

        if ($request->filled('customer_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $search = '%' . $request->customer_name . '%';
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$search]);
            });
        }

        if ($request->filled('mobile')) {
            $query->whereHas('user', function ($q) use ($request) {
                $mobile = $request->mobile;
                // Normalize Arabic numerals to Western Arabic
                $mobile = strtr($mobile, [
                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                    '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                    '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'
                ]);
                
                $candidates = \App\Support\SaudiPhoneNumber::lookupDigits($mobile);
                if (!empty($candidates)) {
                    $q->whereIn('mobile', $candidates);
                } else {
                    $q->where(function($sub) use ($mobile) {
                        $sub->where('mobile', 'like', '%' . $mobile . '%');
                        
                        // If it starts with +966, also search without +
                        if (str_starts_with($mobile, '+')) {
                            $sub->orWhere('mobile', 'like', '%' . substr($mobile, 1) . '%');
                        }
                        
                        // If it starts with 05, it could be stored as +9665 or 9665
                        if (str_starts_with($mobile, '05')) {
                            $suffix = substr($mobile, 1);
                            $sub->orWhere('mobile', 'like', '%+966' . $suffix . '%')
                               ->orWhere('mobile', 'like', '%966' . $suffix . '%');
                        }
                        
                        // If it starts with 9665 or +9665, it could be stored as 05
                        if (str_starts_with($mobile, '9665')) {
                            $suffix = substr($mobile, 3);
                            $sub->orWhere('mobile', 'like', '%0' . $suffix . '%');
                        }
                        if (str_starts_with($mobile, '+9665')) {
                            $suffix = substr($mobile, 4);
                            $sub->orWhere('mobile', 'like', '%0' . $suffix . '%');
                        }
                    });
                }
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();
        $invoices->each(function (Invoice $invoice) {
            $invoice->setAttribute('display_total', $this->resolveInvoiceDisplayTotal($invoice));
        });

        return view('backend.invoice.index_datatable', compact('module_action', 'invoices', 'module_title'));
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return redirect()->back()->with('success', __('messages.deleted_successfully'));
    }

    private function resolveInvoiceDisplayTotal(Invoice $invoice): float
    {
        $storedTotal = (float) ($invoice->final_total ?? 0);
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
        $giftIds = $this->normalizeIds($invoice->gift_ids ?? []);

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
