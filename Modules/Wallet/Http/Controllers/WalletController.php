<?php

namespace Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHistory;
use App\Services\TapPaymentService;
use Illuminate\Support\Facades\DB;
use Currency;
use carbon\Carbon;

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
        $tap = new TapPaymentService();
        $user = auth()->user();
        $amount = $request->amount;
        
        if (!$amount || !is_numeric($amount)) {
            return redirect()->back()->with('error', __('messages.invalid_amount'));
        }
        
        session([
            'walletToAdd' => (float) $amount,
        ]);

        $charge = $tap->createCharge(
            amount: $amount,
            customerData: [
                "name"         => $user->first_name . $user->last_name ,
                "country_code" => "966",
                "phone"        => $user->mobile,
                "method"       => 'src_card', 
            ],
            redirectUrl: route('addbalance.callback')
        );
        if (!isset($charge['transaction']['url'])) {
            return "Error: " . json_encode($charge);
        }

        $paymentUrl = $charge['transaction']['url'];
        
        $lang = app()->getLocale() ?? 'en';
        
        $paymentUrl = preg_replace('/([&?])language=[^&]+/', '$1language=' . $lang, $paymentUrl);
        
        if (!str_contains($paymentUrl, 'language=')) {
            $paymentUrl .= (str_contains($paymentUrl, '?') ? '&' : '?') . 'language=' . $lang;
        }
        
        return redirect()->away($paymentUrl);
    }
    
    public function callback(Request $request)
    {
        $tap = new TapPaymentService();
        
        $failed = function($message, $sub = '', $redirect = '/') {
            $datas = [ 'message' => $message, 'sub' => $sub];
            return view('components.frontend.status.FAILED', $datas);
        };
        
        $amount = (float) session('walletToAdd');
        if (!$amount || $amount <= 0) {
            return $failed(__('messages.invalid_amount'));
        }
        
        $chargeId = $request->tap_id;
        if (!$chargeId) {
            return "خطأ: لم يتم العثور على معرف العملية tap_id";
        }

        $alreadyExists = WalletHistory::where('activity_type', 'deposit')->where('activity_data->tap_id', $chargeId)->exists();
        
        if ($alreadyExists) {
            return $failed(__('messages.duplicate_payment'));
        }

        $charge = $tap->getCharge($chargeId);
        if (!isset($charge['status'])) {
            return $failed("خطأ غير متوقع: " . json_encode($charge), '');
        }


        $status = $charge['status'];
        switch ($status) {
            case "CAPTURED":
                DB::transaction(function () use ($amount, $chargeId, $charge) {

                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => auth()->id()],
                        [
                            'amount' => 0,
                            'title' => auth()->user()->first_name . ' ' . auth()->user()->second_name,
                        ]
                    );

                    $wallet->increment('amount', $amount);
                
                    WalletHistory::create([
                        'datetime'         => now(),
                        'user_id'          => auth()->id(),
                        'activity_type'    => 'deposit',
                        'activity_message' => 'Wallet balance added',
                        'activity_data'    => json_encode([
                            'tap_id' => $chargeId,
                            'credit_debit_amount' => $amount,
                            'status' => $charge['status'],
                        ]),
                    ]);
                });                
                session()->forget('walletToAdd');
                return view('components.frontend.status.CAPTURED');
            case "FAILED":
                session()->forget('walletToAdd');
                return $failed(__('messages.failed_status'), __('messages.failed_message'));
            case "CANCELLED":
                session()->forget('walletToAdd');
                return $failed(__('messages.cancelled_status'), __('messages.cancelled_message'));
        
            case "INITIATED":
                session()->forget('walletToAdd');
                return $failed(__('messages.initiated_status'), __('messages.initiated_message'));
            default:
                session()->forget('walletToAdd');
                return $failed(__('messages.unknown_status') . ": " . $status);
        }
    }
}
