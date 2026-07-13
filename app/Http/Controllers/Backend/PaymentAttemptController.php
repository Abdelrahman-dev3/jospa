<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

class PaymentAttemptController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_invoice')->only(['index']);
    }

    public function index(Request $request)
    {
        $module_title = __('sidebar.Payment Transactions');
        $module_action = 'List';

        $query = PaymentAttempt::with(['user', 'invoice'])->latest();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

        if ($request->filled('gateway')) {
            $query->where('gateway', (string) $request->gateway);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('transaction_id')) {
            $search = trim((string) $request->transaction_id);
            $query->where(function ($attemptQuery) use ($search) {
                $attemptQuery
                    ->where('gateway_transaction_id', 'like', '%' . $search . '%')
                    ->orWhere('gateway_checkout_id', 'like', '%' . $search . '%')
                    ->orWhere('gateway_order_id', 'like', '%' . $search . '%')
                    ->orWhere('merchant_reference', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('customer')) {
            $search = trim((string) $request->customer);
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where(function ($nested) use ($search) {
                    $nested
                        ->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ['%' . $search . '%'])
                        ->orWhere('mobile', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        $attempts = $query->paginate(20)->withQueryString();
        $gateways = PaymentAttempt::query()->select('gateway')->distinct()->pluck('gateway')->filter()->sort()->values();

        $statsBase = PaymentAttempt::query();
        $stats = [
            'total' => (clone $statsBase)->count(),
            'paid' => (clone $statsBase)->where('status', PaymentAttempt::STATUS_PAID)->count(),
            'failed' => (clone $statsBase)->whereIn('status', [PaymentAttempt::STATUS_FAILED, PaymentAttempt::STATUS_CANCELLED])->count(),
            'pending' => (clone $statsBase)->whereIn('status', [PaymentAttempt::STATUS_INITIATED, PaymentAttempt::STATUS_PENDING])->count(),
            'paid_amount' => (float) ((clone $statsBase)->where('status', PaymentAttempt::STATUS_PAID)->sum('amount')),
        ];

        return view('backend.payment_attempts.index', compact(
            'module_title',
            'module_action',
            'attempts',
            'gateways',
            'stats'
        ));
    }
}
