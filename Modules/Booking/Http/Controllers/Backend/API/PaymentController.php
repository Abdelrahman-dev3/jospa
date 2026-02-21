<?php

namespace Modules\Booking\Http\Controllers\Backend\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingTransaction;
use Modules\Booking\Trait\PaymentTrait;
use Modules\Commission\Models\CommissionEarning;
use Modules\Package\Models\BookingPackages;
use Modules\Package\Models\UserPackage;
use Modules\Tip\Models\TipEarning;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHistory;
use Illuminate\Support\Facades\Log;


class PaymentController extends Controller
{
    use PaymentTrait;

    public function __construct()
    {
        // Page Title
        $this->module_title = 'Payment';
    }

    public function savePayment(Request $request)
{
    try {
        $data = $request->all();
        $data['tip_amount'] = $data['tip'] ?? 0;

        $booking = Booking::where('id', $data['booking_id'])->first();

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found.'
            ], 404); // Not Found
        }

        $payment = BookingTransaction::create($data);

        if ($data['transaction_type'] == 'wallet') {
            $total_amount = $this->getTotalAmount($data['booking_id'], $data['tax_percentage'], $data['tip']);
            $user_id = Booking::find($data['booking_id'])->user_id;

            if (!$user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.'
                ], 404); // Not Found
            }

            $wallet = Wallet::where('user_id', $user_id)->first();

            if (!$wallet) {
                return response()->json([
                    'status' => false,
                    'message' => 'Wallet not found.'
                ], 404); // Not Found
            }

            if ($wallet->amount >= $total_amount) {
                $wallet->amount -= $total_amount;
                if ($wallet->save()) {
                    // $activity_message = 'Wallet payment of ' . $total_amount;
                    $activity_message = __('messages.wallet_paid') . $data['booking_id'];

                    $activity_data = [
                        'title' => $wallet->title,
                        'amount' => $wallet->amount,
                        'transaction_id' => $data['external_transaction_id'],
                        'transaction_type' => 'wallet',
                        'credit_debit_amount' => (float) $total_amount,
                        'transaction_type' => 'debit',
                    ];
                    $walletHistoryData = [
                        'user_id' => $wallet->user_id,
                        'datetime' => now(),
                        'activity_type' => 'debit',
                        'activity_message' => $activity_message,
                        'activity_data' => json_encode($activity_data),
                    ];
                    WalletHistory::create($walletHistoryData);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Failed to update wallet balance.'
                    ], 500); // Internal Server Error
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient wallet balance.'
                ], 400); // Bad Request
            }
        }

        $earning_data = $this->commissionData($payment);
        $this->storeApiUserPackage($data['booking_id']);

        if (isset($earning_data['commission_data']) && $earning_data['commission_data'] != null) {
            $booking->commission()->save(new CommissionEarning($earning_data['commission_data']));
        }

        if ($data['tip_amount'] > 0) {
            $booking->tip()->save(new TipEarning([
                'employee_id' => $earning_data['employee_id'],
                'tip_amount' => number_format($data['tip_amount'], 2),
                'tip_status' => 'unpaid',
                'payment_date' => null,
            ]));
        }

        return response()->json([
            'message' => __('booking.payment_done'),
            'status' => true
         ], 200); // OK
    } catch (\Exception $e) {
        // Log the exception for debugging purposes
        Log::error('Payment Save Error: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'An error occurred while processing the payment.'
        ], 500); // Internal Server Error
    }
}
}
