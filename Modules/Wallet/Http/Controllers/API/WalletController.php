<?php

namespace Modules\Wallet\Http\Controllers\API;

use App\Models\User;
use Modules\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Wallet\Http\Requests\WalletRequest;
use Modules\Wallet\Models\WalletHistory;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Modules\Wallet\Models\WithdrawMoney;

class WalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('wallet::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('wallet::create');
    }

    /**
     * Store a newly created resource in storage.
     */


    public function store(WalletRequest $request)
    {
        $user = auth()->user();

        $existingWallet = Wallet::where('user_id', $user->id)->first();

        if ($existingWallet) {
            return response()->json([
                'status' => false,
                'message' => 'User already has a wallet',
                'data' => $existingWallet,
            ], 400);
        }

        $data = $request->all();
        $data['user_id'] = $user->id; // Assign the user ID
        $data['title'] = $user->first_name . ' ' . $user->last_name; // Store the user's name in the title field

        $wallet = Wallet::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Wallet created successfully.',
            'data' => $wallet,
        ], 201);
    }

    public function getBalance()
    {
        $user = auth()->user();


        $wallet = Wallet::where('user_id', $user->id)->first();

        return response()->json([
            'balance' => $wallet ? $wallet->amount : 0,
        ], 200);
    }


    /**
     * Disabled: this endpoint credited whatever amount the client sent without
     * checking any payment. Top-ups now go through POST /api/wallet/topup/init,
     * and the server credits the wallet itself after verifying with the gateway.
     */
    public function walletTopup(Request $request)
    {
        Log::warning('Blocked call to disabled wallet top-up endpoint.', [
            'user_id' => auth()->id(),
            'path' => $request->path(),
            'amount' => $request->input('amount'),
            'transaction_id' => $request->input('transaction_id'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => false,
            'error_code' => 'endpoint_removed',
            'message' => 'This endpoint is disabled. Use POST /api/wallet/topup/init and GET /api/wallet/topup/status/{attempt_id}.',
        ], 410);
    }


    public function walletHistory(Request $request)
    {

        $perPage = $request->get('per_page', 25);
        $orderBy = $request->get('orderby', 'desc');


        $userId = auth()->user()->id;

        $walletHistories = WalletHistory::where('user_id', $userId)
            ->orderBy('created_at', $orderBy)
            ->paginate($perPage);

        $wallet = Wallet::where('user_id', $userId)->first();
        $availableBalance = $wallet ? $wallet->amount : 0;

        $timezone = setting('default_time_zone') ?? 'UTC';

        $walletHistories->getCollection()->transform(function ($item) use ($timezone) {

            $item->datetime = Carbon::parse($item->datetime)
                ->setTimezone($timezone)
                ->toDateTimeString();  
            return $item;
        });        

        $response = [
            'pagination' => [
                'total' => $walletHistories->total(),
                'per_page' => $walletHistories->perPage(),
                'current_page' => $walletHistories->currentPage(),
                'last_page' => $walletHistories->lastPage(),
                'from' => $walletHistories->firstItem(),
                'to' => $walletHistories->lastItem(),
                'next_page' => $walletHistories->nextPageUrl(),
                'previous_page' => $walletHistories->previousPageUrl(),
            ],
            'data' => $walletHistories->items(),
            'available_balance' => $availableBalance,

        ];

        return response()->json($response);
    }

    public function withdrawMoney(Request $request)
    {
        $data = $request->except('_token');

        $payment_gateway = $data['payment_gateway'];
        $user_id = $data['user_id'];

        // Fetch user's wallet details
        $wallet = Wallet::where('user_id', $user_id)->first();

        if (!$wallet) {
            return response()->json([
                'status' => false,
                'message' => 'Wallet not found for user.'
            ], 404);
        }

        if ($wallet->amount < $request->amount) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient balance to withdraw.'
            ], 400);
        }

        $payout_status = '';
        $status = '';

        // Check the payment method (bank)
        if ($data['payment_method'] === 'bank') {

            switch ($payment_gateway) {

                    // RazorpayX case
                case 'razorpayx':

                    // Call the provider payout function from helpers.php
                    $response = providerpayout_rezopayX($data);

                    if ($response == '') {
                        // Handle failure if response is empty
                        $data['bank_id'] = $data['bank_id'];
                        $data['payment_type'] = $payment_gateway;
                        $data['datetime'] = Carbon::now();
                        $data['status'] = 'failed';
                        WithdrawMoney::create($data);

                        return response()->json([
                            'status' => false,
                            'message' => 'Error with RazorpayX details.'
                        ], 500);
                    }

                    $payout_details = json_decode($response, true);

                    // Check if payout response is valid
                    if (isset($payout_details['status']) && $payout_details['status'] == 'processing') {
                        $data['status'] = 'paid';
                    }

                    // Check for Razorpay error and handle accordingly
                    if (isset($payout_details['error'])) {
                        // Safely access 'description' if it exists
                        $razorpay_message = isset($payout_details['error']['description']) ? $payout_details['error']['description'] : 'Unknown error occurred';

                        if (isset($payout_details['error']['code']) && $payout_details['error']['code'] == 'BAD_REQUEST_ERROR') {
                            return response()->json([
                                'status' => false,
                                'message' => 'Razorpay Error: ' . $razorpay_message
                            ], 406);
                        }

                        // If error is not related to bad request, mark the transaction as failed
                        $data['bank_id'] = $data['bank_id'];
                        $data['payment_type'] = $payment_gateway;
                        $data['datetime'] = Carbon::now();
                        $data['status'] = 'failed';
                        WithdrawMoney::create($data);

                        return response()->json([
                            'status' => false,
                            'message' => 'Razorpay Error: ' . $razorpay_message
                        ], 500);
                    }
                    break;
            }
        }

        // Save withdrawal request in the database
        $data['bank_id'] = $data['bank_id'];
        $data['payment_type'] = $payment_gateway;
        $data['datetime'] = Carbon::now();
        WithdrawMoney::create($data);

        // Deduct the withdrawal amount from the wallet balance
        $wallet->amount -= $data['amount'];
        $wallet->save();

        $activity_message =  __('messages.amount_withdrawn');

        // Prepare activity data for the WalletHistory entry
        $activity_data = [
            'title' => $wallet->title,
            'amount' => $wallet->amount,
            'transaction_id' => "",
            'transaction_type' => 'debit', // Withdrawals are considered as debits
            'credit_debit_amount' => (float) $data['amount'],
        ];

        // Prepare data for the WalletHistory entry
        $history_data = [
            'user_id' => $wallet->user_id,
            'datetime' => now(),
            'activity_type' => 'debit', // Debit for withdrawal
            'activity_message' => $activity_message,
            'activity_data' => json_encode($activity_data),
        ];

        // Create an entry in the WalletHistory table for the withdrawal
        WalletHistory::create($history_data);

        // Return response to the user (API or Web)
        return response()->json([
            'status' => true,
            'message' => 'Money transferred successfully.'
        ], 200);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('wallet::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('wallet::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
