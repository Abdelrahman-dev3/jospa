<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

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

        return view('backend.invoice.index_datatable', compact('module_action', 'invoices', 'module_title'));
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return redirect()->back()->with('success', __('messages.deleted_successfully'));
    }
}
