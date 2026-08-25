<?php

namespace Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHistory;
use App\Models\User;
use App\Services\HyperpayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Currency;
use carbon\Carbon;
use App\Services\UserNotificationService;

class WalletController extends Controller
{
    public function __construct()
    {
        // Page Title
        $this->module_title = 'Wallet';

        // module name
        $this->module_name = 'wallet';

        // directory path of the module
        $this->module_path = 'Wallet::wallet';

        view()->share([
            'module_title' => $this->module_title,
            'module_icon' => 'fa-regular fa-sun',
            'module_name' => $this->module_name,
            'module_path' => $this->module_path,
        ]);
    }

    public function walletHistory($id)
    {
        $module_title = __('messages.wallet_history');
        $module_action = 'List';
        $user_id = $id;

        return view('wallet::wallet_history.index_datatable', compact('module_title', 'module_action', 'user_id'));
    }

    public function walletHistoryData(Datatables $datatable, Request $request)
    {
        $query = WalletHistory::where('user_id', $request->id);

        return $datatable->eloquent($query)
            
            ->editColumn('datetime', function ($data) {
                $timezone = setting('default_time_zone') ?? 'UTC';
                return Carbon::parse($data->datetime)->setTimezone($timezone)->format('Y-m-d H:i:s');
            })
            ->editColumn('activity_type', function ($data) {
                return str_replace("_"," ",ucfirst($data->activity_type));
            })
            
            ->editColumn('amount', function ($data) {
                $wallet = json_decode($data->activity_data); 
                return Currency::format($wallet->credit_debit_amount);
            })
            ->filterColumn('amount', function($query, $keyword) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(activity_data, '$.credit_debit_amount')) LIKE ?", ["%{$keyword}%"]);
            })
            ->orderColumn('amount', function ($query, $order) {
                $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(activity_data, '$.credit_debit_amount')) AS DECIMAL(15,2)) {$order}");
            })
            
            
            ->rawColumns(['activity_type','amount'])
            ->orderColumns(['id'], '-:column $1')
            ->make(true);
    }

    public function addbalance(Request $request)
    {
        $user = auth()->user();
        $amount = (float) $request->amount;
        
        if (! $user) {
            return redirect('/signin');
        }

        if (!$amount || $amount <= 0) {
            return redirect()->back()->with('error', __('messages.invalid_amount'));
        }

        $brand = $this->normalizeHyperpayBrand($request->get('brand', 'VISA'));
        $merchantTransactionId = $this->generateWalletMerchantTransactionId();
        $resultUrl = route('addbalance.callback', [
            'mtid' => $merchantTransactionId,
            'brand' => $brand,
        ]);

        try {
            $hyperpay = app(HyperpayService::class);
            $checkout = $hyperpay->createCheckout(
                $amount,
                $merchantTransactionId,
                $resultUrl,
                $this->buildHyperpayCustomerData($user),
                $brand
            );
        } catch (\Throwable $e) {
            Log::error('Wallet Hyperpay checkout creation failed.', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }

        $checkoutId = (string) ($checkout['id'] ?? '');
        if ($checkoutId === '') {
            return redirect()->back()->with('error', app()->getLocale() === 'ar'
                ? 'تعذر إنشاء جلسة الدفع في Hyperpay.'
                : 'Failed to create Hyperpay checkout.'
            );
        }

        $context = [
            'user_id' => (int) $user->id,
            'amount' => $amount,
            'checkout_id' => $checkoutId,
            'brand' => $brand,
            'merchant_transaction_id' => $merchantTransactionId,
        ];

        Cache::put($this->walletPaymentCacheKey($merchantTransactionId), $context, now()->addMinutes(40));
        session([
            'walletToAdd' => $amount,
            'walletTopupMtid' => $merchantTransactionId,
            'walletTopupCheckoutId' => $checkoutId,
            'walletTopupBrand' => $brand,
        ]);

        return redirect()->to(URL::temporarySignedRoute(
            'wallet.hyperpay.checkout',
            now()->addMinutes(30),
            [
                'checkoutId' => $checkoutId,
                'mtid' => $merchantTransactionId,
                'brand' => $brand,
            ]
        ));
    }

    public function hyperpayCheckout(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $merchantTransactionId = (string) $request->get('mtid', '');
        $checkoutId = (string) $request->get('checkoutId', '');
        $brand = $this->normalizeHyperpayBrand($request->get('brand', 'VISA'));
        $context = $this->resolveWalletPaymentContext($merchantTransactionId);

        if (! $context || ($context['checkout_id'] ?? null) !== $checkoutId) {
            return view('frontend.payment-status.failed', [
                'message' => app()->getLocale() === 'ar'
                    ? 'انتهت جلسة الدفع. حاول مرة أخرى.'
                    : 'Payment session expired. Please try again.',
            ]);
        }

        [$widgetBrands, $brandLabels] = $this->widgetConfigurationForBrand($brand);

        return view('frontend.payment-status.hyperpay', [
            'widgetScriptUrl' => app(HyperpayService::class)->widgetScriptUrl($checkoutId, $brand),
            'brands' => $widgetBrands,
            'brandLabels' => $brandLabels,
            'resultUrl' => route('addbalance.callback', [
                'mtid' => $merchantTransactionId,
                'brand' => $brand,
            ]),
        ]);
    }
    
    public function callback(Request $request)
    {
        $failed = function($message, $sub = '', $redirect = '/') {
            $datas = [ 'message' => $message, 'sub' => $sub];
            return view('frontend.payment-status.failed', $datas);
        };

        $merchantTransactionId = (string) $request->get('mtid', session('walletTopupMtid', ''));
        $brand = $this->normalizeHyperpayBrand($request->get('brand', session('walletTopupBrand', 'VISA')));
        $resourcePath = (string) $request->get('resourcePath', '');
        $context = $this->resolveWalletPaymentContext($merchantTransactionId);

        if (! $context) {
            return $failed(app()->getLocale() === 'ar'
                ? 'انتهت جلسة الدفع. حاول مرة أخرى.'
                : 'Payment session expired. Please try again.'
            );
        }

        $amount = (float) ($context['amount'] ?? 0);
        if ($amount <= 0) {
            $this->clearWalletPaymentState($merchantTransactionId);
            return $failed(__('messages.invalid_amount'));
        }

        if ($resourcePath === '') {
            return $failed(app()->getLocale() === 'ar'
                ? 'تعذر التحقق من عملية الدفع.'
                : 'Unable to verify payment.'
            );
        }

        try {
            $hyperpay = app(HyperpayService::class);
            $payment = $this->fetchWalletPaymentStatusWithFallback($hyperpay, $resourcePath, $brand, $merchantTransactionId, $request->all());
        } catch (\Throwable $e) {
            Log::error('Wallet Hyperpay verification failed.', [
                'merchant_transaction_id' => $merchantTransactionId,
                'resource_path' => $resourcePath,
                'error' => $e->getMessage(),
            ]);

            return $failed($e->getMessage());
        }

        $resultCode = (string) data_get($payment, 'result.code', '');
        if (! $hyperpay->isSuccessfulResult($resultCode)) {
            $this->clearWalletPaymentState($merchantTransactionId);

            return $failed((string) data_get(
                $payment,
                'result.description',
                app()->getLocale() === 'ar' ? 'فشلت عملية الدفع.' : 'Payment failed.'
            ));
        }

        if ($hyperpay->isTestModeResult($resultCode)) {
            $this->clearWalletPaymentState($merchantTransactionId);
            return $failed($hyperpay->testModeResultMessage($resultCode));
        }

        $paidAmount = (float) data_get($payment, 'amount', 0);
        if ($paidAmount > 0 && abs($paidAmount - $amount) > 0.01) {
            $this->clearWalletPaymentState($merchantTransactionId);

            return $failed(app()->getLocale() === 'ar'
                ? 'قيمة الدفع لا تطابق المبلغ المطلوب.'
                : 'Paid amount does not match the requested amount.'
            );
        }

        $transactionId = (string) (data_get($payment, 'id') ?: $request->get('id') ?: $merchantTransactionId);
        $alreadyExists = WalletHistory::where('activity_type', 'deposit')
            ->where(function ($query) use ($transactionId, $merchantTransactionId) {
                $query->where('activity_data->hyperpay_transaction_id', $transactionId)
                    ->orWhere('activity_data->merchant_transaction_id', $merchantTransactionId);
            })
            ->exists();

        if ($alreadyExists) {
            $this->clearWalletPaymentState($merchantTransactionId);
            return $failed(__('messages.duplicate_payment'));
        }

        $userId = (int) ($context['user_id'] ?? 0);
        $user = User::find($userId);
        if (! $user) {
            $this->clearWalletPaymentState($merchantTransactionId);
            return $failed(__('messages.user_notfound'));
        }

        DB::transaction(function () use ($amount, $transactionId, $merchantTransactionId, $payment, $user, $context) {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'amount' => 0,
                    'title' => trim((string) ($user->first_name . ' ' . ($user->last_name ?? $user->second_name ?? ''))),
                    'status' => 1,
                ]
            );

            $wallet->increment('amount', $amount);

            WalletHistory::create([
                'datetime'         => now(),
                'user_id'          => $user->id,
                'activity_type'    => 'deposit',
                'activity_message' => 'Wallet balance added',
                'activity_data'    => json_encode([
                    'gateway' => 'hyperpay',
                    'hyperpay_transaction_id' => $transactionId,
                    'merchant_transaction_id' => $merchantTransactionId,
                    'checkout_id' => $context['checkout_id'] ?? null,
                    'credit_debit_amount' => $amount,
                    'status' => data_get($payment, 'result.description', data_get($payment, 'result.code')),
                ]),
            ]);
        });

        $this->clearWalletPaymentState($merchantTransactionId);

        // Send wallet top-up notification
        app(UserNotificationService::class)->notifyWalletTopUp($user, $amount);

        return view('frontend.payment-status.captured');
    }

    private function buildHyperpayCustomerData($user): array
    {
        $givenName = trim((string) ($user->first_name ?? ''));
        $surname = trim((string) ($user->last_name ?? $user->second_name ?? ''));
        $country = strtoupper(trim((string) ($user->country ?? 'SA')));

        if (strlen($country) !== 2) {
            $country = 'SA';
        }

        return [
            'given_name' => $givenName !== '' ? $givenName : 'Customer',
            'surname' => $surname !== '' ? $surname : ($givenName !== '' ? $givenName : 'Customer'),
            'email' => $user->email,
            'mobile' => $user->mobile,
            'billing_street' => trim((string) ($user->address ?? 'Main Street 3252')),
            'billing_city' => trim((string) ($user->city ?? 'Riyadh')),
            'billing_state' => trim((string) ($user->city ?? 'Riyadh')),
            'billing_country' => $country,
            'billing_postcode' => '25262',
        ];
    }

    private function generateWalletMerchantTransactionId(): string
    {
        return 'W' . Str::upper(Str::random(15));
    }

    private function walletPaymentCacheKey(string $merchantTransactionId): string
    {
        return 'wallet_hyperpay_' . $merchantTransactionId;
    }

    private function resolveWalletPaymentContext(string $merchantTransactionId): ?array
    {
        if ($merchantTransactionId !== '') {
            $context = Cache::get($this->walletPaymentCacheKey($merchantTransactionId));
            if (is_array($context)) {
                return $context;
            }
        }

        if ((string) session('walletTopupMtid') !== $merchantTransactionId) {
            return null;
        }

        $amount = (float) session('walletToAdd', 0);
        $userId = auth()->id();

        if ($amount <= 0 || ! $userId) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'amount' => $amount,
            'checkout_id' => session('walletTopupCheckoutId'),
            'brand' => session('walletTopupBrand', 'VISA'),
            'merchant_transaction_id' => $merchantTransactionId,
        ];
    }

    private function clearWalletPaymentState(string $merchantTransactionId): void
    {
        if ($merchantTransactionId !== '') {
            Cache::forget($this->walletPaymentCacheKey($merchantTransactionId));
        }

        session()->forget([
            'walletToAdd',
            'walletTopupMtid',
            'walletTopupCheckoutId',
            'walletTopupBrand',
        ]);
    }

    private function normalizeHyperpayBrand(mixed $brand): string
    {
        $brand = strtoupper(trim((string) $brand));

        return match ($brand) {
            'MADA' => 'MADA',
            'APPLEPAY', 'APPLE_PAY', 'APPLE' => 'APPLEPAY',
            'MASTER', 'MASTERCARD', 'VISA', '' => 'VISA',
            default => 'VISA',
        };
    }

    private function widgetConfigurationForBrand(string $brand): array
    {
        return match ($brand) {
            'MADA' => ['MADA', ['Mada']],
            'APPLEPAY' => ['APPLEPAY', ['Apple Pay']],
            default => ['VISA MASTER', ['Visa', 'Mastercard']],
        };
    }

    private function fetchWalletPaymentStatusWithFallback(
        HyperpayService $hyperpay,
        string $resourcePath,
        string $brand,
        string $merchantTransactionId,
        array $callbackPayload = []
    ): array {
        try {
            return $hyperpay->fetchPaymentStatus($resourcePath, $brand);
        } catch (\RuntimeException $exception) {
            $message = strtolower($exception->getMessage());
            if (! str_contains($message, 'invalid or missing parameter') && ! str_contains($message, 'verification request')) {
                throw $exception;
            }

            $alternateBrand = $brand === 'MADA' ? 'VISA' : 'MADA';

            Log::warning('Retrying wallet Hyperpay status fetch with alternate brand entity.', [
                'merchant_transaction_id' => $merchantTransactionId,
                'resource_path' => $resourcePath,
                'primary_brand' => $brand,
                'alternate_brand' => $alternateBrand,
                'message' => $exception->getMessage(),
                'callback_payload' => $callbackPayload,
            ]);

            return $hyperpay->fetchPaymentStatus($resourcePath, $alternateBrand);
        }
    }
}
