<?php

namespace App\Http\Controllers;

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
        $gifts = GiftCard::all();
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

        $gift = GiftCard::where( 'ref' , $code )->where( 'payment_status' , 1 )->first();
        
        if (!$gift) {
            return response()->json([
                'status'  => false,
                'message' => __('messagess.invalid_gift_code')
            ], 404);
        }
    
        return response()->json([
            'status'  => true,
            'balance' => $gift->balance,
            'message' => __('messagess.gift_code_valid') 
        ], 200);

    }
    
    

}
