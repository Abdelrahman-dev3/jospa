<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Branch;
use App\Models\GiftCard;

class GiftController extends Controller
{
    /** delivery_method values that mean an electronic (emailed) card. */
    public const ELECTRONIC_METHODS = ['electronic_card', 'email', 'بطاقة الكترونية'];

    public function __construct()
    {
        $this->middleware('permission:view_gift')->only(['index', 'lookup']);
        $this->middleware('permission:delete_gift')->only('destroy');
    }


    public function index(Request $request)
    {
        $module_action = 'List';
        $module_title = 'Gift Cards';

        $query = GiftCard::with(['user', 'branch'])->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('ref', 'like', $like)
                    ->orWhere('sender_name', 'like', $like)
                    ->orWhere('sender_phone', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('recipient_phone', 'like', $like)
                    ->orWhere('bouquet_name', 'like', $like)
                    ->orWhereHas('user', function ($u) use ($like) {
                        $u->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('mobile', 'like', $like);
                    });
            });
        }

        $paymentStatus = $request->query('payment_status');
        if ($paymentStatus === 'paid') {
            $query->where('payment_status', 1);
        } elseif ($paymentStatus === 'pending') {
            $query->where(fn ($q) => $q->where('payment_status', '!=', 1)->orWhereNull('payment_status'));
        }

        $delivery = $request->query('delivery');
        if ($delivery === 'electronic') {
            $query->whereIn('delivery_method', self::ELECTRONIC_METHODS);
        } elseif ($delivery === 'traditional') {
            $query->where(fn ($q) => $q->whereNotIn('delivery_method', self::ELECTRONIC_METHODS)->orWhereNull('delivery_method'));
        }

        $type = $request->query('type');
        if ($type === 'branch') {
            $query->whereNotNull('branch_id');
        } elseif ($type === 'regular') {
            $query->whereNull('branch_id');
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        $balance = $request->query('balance');
        if ($balance === 'has_balance') {
            $query->where('balance', '>', 0);
        } elseif ($balance === 'used') {
            $query->whereNotNull('ref')->where('ref', '!=', '')
                ->where(fn ($q) => $q->where('balance', '<=', 0)->orWhereNull('balance'));
        } elseif ($balance === 'has_ref') {
            $query->whereNotNull('ref')->where('ref', '!=', '');
        } elseif ($balance === 'no_ref') {
            $query->where(fn ($q) => $q->whereNull('ref')->orWhere('ref', ''));
        }

        $hasMessage = $request->query('has_message');
        if ($hasMessage === 'yes') {
            $query->whereNotNull('message')->where('message', '!=', '');
        } elseif ($hasMessage === 'no') {
            $query->where(fn ($q) => $q->whereNull('message')->orWhere('message', ''));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }
        if ($request->filled('amount_min')) {
            $query->where('subtotal', '>=', (float) $request->query('amount_min'));
        }
        if ($request->filled('amount_max')) {
            $query->where('subtotal', '<=', (float) $request->query('amount_max'));
        }

        $gifts = $query->get();
        $branches = Branch::query()->orderBy('id')->get(['id', 'name']);

        return view('backend.gift.index_datatable', compact('module_action', 'gifts', 'module_title', 'branches'));
    }

    /**
     * Look a gift card code up in the website database and in Odoo, and
     * return what each side knows so the dashboard can show it in a popup.
     */
    public function lookup(Request $request)
    {
        $code = trim((string) $request->query('code'));

        if ($code === '') {
            return response()->json(['status' => false, 'message' => 'من فضلك أدخل كود الجيفت كارد'], 422);
        }

        $gift = GiftCard::withTrashed()->with(['user', 'branch'])->where('ref', $code)->first();

        $website = ['found' => false];
        if ($gift) {
            $isElectronic = in_array($gift->delivery_method, self::ELECTRONIC_METHODS, true);
            $website = [
                'found' => true,
                'id' => $gift->id,
                'ref' => $gift->ref,
                'balance' => $gift->balance,
                'subtotal' => $gift->subtotal,
                'card_amount' => $gift->card_amount,
                'paid' => (int) $gift->payment_status === 1,
                'deleted' => $gift->trashed(),
                'delivery' => $isElectronic ? __('messagess.email_delivery') : __('messagess.traditional_gift_card'),
                'customer' => $gift->user ? trim($gift->user->first_name . ' ' . $gift->user->last_name) : null,
                'customer_email' => $gift->user->email ?? null,
                'customer_phone' => $gift->user->mobile ?? null,
                'sender_name' => $gift->sender_name,
                'sender_phone' => $gift->sender_phone,
                'recipient_name' => $gift->recipient_name,
                'recipient_phone' => $gift->recipient_phone,
                'message' => $gift->message,
                'branch' => optional($gift->branch)->name,
                'bouquet_name' => $gift->bouquet_name,
                'bouquet_price' => $gift->bouquet_price,
                'services' => $gift->services_list->pluck('name')->values(),
                'packages' => $gift->packages->pluck('name')->values(),
                'coupons' => collect($gift->coupons)->pluck('name')->filter()->values(),
                'pdf_url' => $gift->pdf_url,
                'created_at' => optional($gift->created_at)->format('Y-m-d H:i'),
                'updated_at' => optional($gift->updated_at)->format('Y-m-d H:i'),
            ];
        }

        $odoo = $this->checkOdoo($code);

        return response()->json([
            'status' => $website['found'] || $odoo['found'],
            'code' => $code,
            'website' => $website,
            'odoo' => $odoo,
        ]);
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

        // 1. Check local database first
        $localGiftCard = GiftCard::where('ref', $code)->where('payment_status', 1)->first();

        if ($localGiftCard && $localGiftCard->balance > 0) {
            return response()->json([
                'status'  => true,
                'balance' => $localGiftCard->balance,
                'message' => __('messagess.gift_code_valid')
            ], 200);
        }

        // 2. Fallback to Odoo
        $odoo = $this->checkOdoo((string) $code);

        if ($odoo['found']) {
            return response()->json([
                'status'  => true,
                'balance' => $odoo['balance'] ?? 0,
                'message' => __('messagess.gift_code_valid')
            ], 200);
        }

        if (! $odoo['reachable']) {
            return response()->json([
                'status'  => false,
                'message' => __('messagess.invalid_gift_code')
            ], 500);
        }

        $httpStatus = $odoo['http_status'];
        $successful = $httpStatus >= 200 && $httpStatus < 300;

        return response()->json([
            'status'  => false,
            'message' => ($successful ? $odoo['message'] : null) ?? __('messagess.invalid_gift_code')
        ], ($successful || $httpStatus === 404) ? 404 : 400);
    }

    /**
     * Ask Odoo about a gift card code.
     *
     * Returns reachable (request completed), found (Odoo says valid),
     * plus balance, message, http_status and the raw response body.
     */
    private function checkOdoo(string $code): array
    {
        $checkUrl = (string) config('services.odoo.giftcard_check_url');
        if (empty($checkUrl)) {
            $bookingCreateUrl = (string) config('services.odoo.booking_create_url');
            $checkUrl = str_replace('/order/create', '/giftcard/check', $bookingCreateUrl);
            $checkUrl = str_replace('/odoo_create_booking', '/giftcard/check', $checkUrl); // fallback
        }

        if (empty($checkUrl)) {
            return ['reachable' => false, 'found' => false, 'message' => 'رابط التحقق من الجيفت كارد في أودو غير مضبوط'];
        }

        $apiKey = (string) config('services.odoo.api_key');

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($apiKey !== '') {
            $headers['api-key'] = $apiKey;
        } else {
            $headers['db'] = (string) config('services.odoo.db');
            $headers['login'] = (string) config('services.odoo.login');
            $headers['password'] = (string) config('services.odoo.password');
        }

        try {
            $response = Http::timeout((int) config('services.odoo.timeout', 15))
                ->withHeaders($headers)
                ->post($checkUrl, ['data' => ['code' => $code]]);

            $body = $response->json();
            $body = is_array($body) ? $body : [];
            $valid = isset($body['valid']) && $body['valid'] === true;

            return [
                'reachable' => true,
                'http_status' => $response->status(),
                'found' => $response->successful() && $valid,
                'valid' => $valid,
                'balance' => $body['balance'] ?? null,
                'message' => $body['message'] ?? null,
                'data' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Odoo giftcard check failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return ['reachable' => false, 'found' => false, 'message' => 'تعذر الاتصال بأودو'];
        }
    }
}
