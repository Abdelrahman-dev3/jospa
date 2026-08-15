<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GiftCard;

class GiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_gift')->only('index');
        $this->middleware('permission:delete_gift')->only('destroy');
    }

    
    public function index()
    {
        $module_action = 'List';
        $module_title = 'Gift Cards';
        $gifts = GiftCard::with('user')->latest('id')->get();
        return view('backend.gift.index_datatable', compact('module_action', 'gifts' , 'module_title'));
    }

    public function destroy($id)
    {
        $gift = GiftCard::findOrFail($id);
        $gift->delete();
        return redirect()->back()->with('success', __('messages.gift_deleted_successfully'));
    }

    public function validateGiftCode(Request $request)
    {
        $code = $request->query('code');

        if (empty($code)) {
            return response()->json([
                'status'  => false,
                'message' => __('messagess.invalid_gift_code')
            ], 400);
        }

        $checkUrl = (string) config('services.odoo.giftcard_check_url');
        if (empty($checkUrl)) {
            $bookingCreateUrl = (string) config('services.odoo.booking_create_url');
            $checkUrl = str_replace('/order/create', '/giftcard/check', $bookingCreateUrl);
            $checkUrl = str_replace('/odoo_create_booking', '/giftcard/check', $checkUrl); // fallback
        }

        $apiKey = (string) config('services.odoo.api_key');
        $db = (string) config('services.odoo.db');
        $login = (string) config('services.odoo.login');
        $password = (string) config('services.odoo.password');

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($apiKey !== '') {
            $headers['api-key'] = $apiKey;
        } else {
            $headers['db'] = $db;
            $headers['login'] = $login;
            $headers['password'] = $password;
        }

        $payload = [
            'data' => [
                'code' => $code
            ]
        ];

        try {
            $response = \Illuminate\Support\Facades\Http::timeout((int) config('services.odoo.timeout', 15))
                ->withHeaders($headers)
                ->post($checkUrl, $payload);

            if ($response->successful()) {
                $body = $response->json();
                
                if (isset($body['valid']) && $body['valid'] === true) {
                    return response()->json([
                        'status'  => true,
                        'balance' => $body['balance'] ?? 0,
                        'message' => __('messagess.gift_code_valid')
                    ], 200);
                }
                
                return response()->json([
                    'status'  => false,
                    'message' => $body['message'] ?? __('messagess.invalid_gift_code')
                ], 404);
            }

            return response()->json([
                'status'  => false,
                'message' => __('messagess.invalid_gift_code')
            ], $response->status() === 404 ? 404 : 400);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Odoo giftcard check failed', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status'  => false,
                'message' => __('messagess.invalid_gift_code')
            ], 500);
        }
    }
    
    

}
