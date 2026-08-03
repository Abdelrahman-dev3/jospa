<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\OdooGiftCardService;

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
        $code = (string) ($request->input('code') ?? $request->query('code', ''));
        $result = app(OdooGiftCardService::class)->check($code);
        $valid = (bool) ($result['valid'] ?? false);
        $statusCode = (int) ($result['status_code'] ?? ($valid ? 200 : 422));

        return response()->json([
            'status' => $valid,
            'valid' => $valid,
            'code' => $result['code'] ?? $code,
            'balance' => (float) ($result['balance'] ?? 0),
            'expiration_date' => $result['expiration_date'] ?? null,
            'expired' => (bool) ($result['expired'] ?? false),
            'partner' => $result['partner'] ?? false,
            'message' => $result['message'] ?? ($valid ? __('messagess.gift_code_valid') : __('messagess.invalid_gift_code')),
        ], $valid ? 200 : ($statusCode === 404 ? 404 : 422));

    }
    
    

}
