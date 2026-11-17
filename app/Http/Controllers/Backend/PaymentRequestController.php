<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Services\PaymentRequest\PaymentRequestService;
use App\Support\SaudiPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Dashboard tab "الفواتير وروابط الدفع". */
class PaymentRequestController extends Controller
{
    public function __construct(private PaymentRequestService $service)
    {
        $this->middleware('permission:view_payment_request')->only(['index', 'refresh']);
        $this->middleware('permission:add_payment_request')->only(['store']);
        $this->middleware('permission:edit_payment_request')->only(['cancel']);
    }

    public function index(Request $request)
    {
        $module_title = __('payment_requests.title');
        $module_action = 'List';

        $query = PaymentRequest::query()->latest('id');

        if ($request->filled('status') && in_array($request->status, PaymentRequest::STATUSES, true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_mobile', 'like', '%'.$search.'%');
            });
        }

        $paymentRequests = $query->paginate(20)->withQueryString();

        return view('backend.payment_requests.index', compact('module_title', 'module_action', 'paymentRequests'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $paymentRequest = $this->service->create($data, auth()->id());

        return redirect()
            ->route('backend.payment-requests.index')
            ->with('success', __('payment_requests.messages.created', ['number' => $paymentRequest->number]))
            ->with('new_payment_request_id', $paymentRequest->id);
    }

    /** Ask HyperPay for the real state of open checkouts (customer paid but never came back). */
    public function refresh(PaymentRequest $paymentRequest)
    {
        $paymentRequest = $this->service->refreshOpenAttempts($paymentRequest, force: true);

        return back()->with('success', __('payment_requests.messages.refreshed', [
            'number' => $paymentRequest->number,
            'status' => __('payment_requests.status.'.$paymentRequest->status),
        ]));
    }

    public function cancel(PaymentRequest $paymentRequest)
    {
        try {
            $this->service->cancel($paymentRequest, auth()->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('payment_requests.messages.cancelled', ['number' => $paymentRequest->number]));
    }

    /** @return array{customer_name: string, customer_mobile: ?string, description: string, amount: string} */
    private function validated(Request $request): array
    {
        // Accept Arabic-Indic digits and thousands separators typed by the admin.
        $amount = strtr(trim((string) $request->input('amount')), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '٬' => '', ',' => '', ' ' => '',
        ]);
        $request->merge(['amount' => $amount]);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => [
                'required',
                'regex:/^\d{1,6}(\.\d{1,2})?$/',
                'numeric',
                'min:'.PaymentRequestService::MIN_AMOUNT,
                'max:'.PaymentRequestService::MAX_AMOUNT,
            ],
        ], [], [
            'customer_name' => __('payment_requests.fields.customer_name'),
            'customer_mobile' => __('payment_requests.fields.customer_mobile'),
            'description' => __('payment_requests.fields.description'),
            'amount' => __('payment_requests.fields.amount'),
        ]);

        $mobile = null;
        if (filled($data['customer_mobile'] ?? null)) {
            $mobile = SaudiPhoneNumber::normalize($data['customer_mobile']);
            if ($mobile === null) {
                throw ValidationException::withMessages([
                    'customer_mobile' => __('payment_requests.errors.invalid_mobile'),
                ]);
            }
        }

        return [
            'customer_name' => trim($data['customer_name']),
            'customer_mobile' => $mobile,
            'description' => trim($data['description']),
            // Decimal string, never a float.
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
        ];
    }
}
