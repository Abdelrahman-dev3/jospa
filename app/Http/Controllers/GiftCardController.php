<?php

namespace App\Http\Controllers;

use App\Models\GiftCard;
use Illuminate\Support\Facades\Http;
use App\Models\Service;
use Illuminate\Http\Request;
use Modules\Category\Models\Category;
use Modules\Package\Models\Package;
use Modules\World\Models\State;
use App\Models\Ad;


use Modules\Service\Models\Service as ServiceModel;

class GiftCardController extends Controller
{

public function index(Request $request)
{

    $ads = Ad::where('page', 'gift_page')->where('status', 1)->get();

    $currentLocale = session('locale', app()->getLocale());

    $s = $request->query('service');

    $subCategories = Category::with(['Services' => function ($q) {
        $q->select('id', 'name', 'default_price','category_id', 'sub_category_id')->where('status', 1);
    }])->whereNull('parent_id')->where('status', 1)->get();

    // Initialize errors if not already set
    if (!isset($errors)) {
        $errors = new \Illuminate\Support\MessageBag();
    }

    // For JSON requests
    if ($request->wantsJson()) {
        return response()->json([
            'success' => true,
            'data' => $subCategories
        ]);
    }
    
    $packages = Package::with(['service', 'service.services', 'media'])
    ->where('status', 1)
    ->whereDate('end_date', '>=', now())
    ->take(6)
    ->get();
    
    $coupons = [
        ['name' => 'كوبون بقيمة 300', 'price' => 300],
        ['name' => 'كوبون بقيمة 400', 'price' => 400],
        ['name' => 'كوبون بقيمة 500', 'price' => 500],
        ['name' => 'كوبون بقيمة 1000', 'price' => 1000],
        ['name' => 'كوبون بقيمة 2000', 'price' => 2000],
        ['name' => 'كوبون بقيمة 3000', 'price' => 3000],
        ['name' => 'كوبون بقيمة 4000', 'price' => 4000],
        ['name' => 'كوبون بقيمة 5000', 'price' => 5000],
    ];

    return view('frontend.gift-cards.create', compact('subCategories', 'errors' ,'packages' , 's' , 'currentLocale' , 'coupons' , 'ads'));
}

public function store(Request $request)
{
    $user = auth()->user();
    $data = $request->all();
    
    if (!$user) {
        session()->put('temp_gift_booking', [
            'data' => $data,
            'created_at' => now(),
        ]);
        return redirect()->route('signup')->with('error', __('messages.login_required_to_continue'));
    }
    $request->session()->regenerate();
    
    if ($request->session()->has('temp_gift_booking')) {
        $data = session('temp_gift_booking.data');
        session()->forget('temp_gift_booking');
    }
    
    $selectedServices = array_map('intval', $data['requested_services']);
    $services = ServiceModel::whereIn('id', $selectedServices)->get();
    $services_total = $services->sum('default_price');


    $total_packages = 0;
    if ($request->filled('package_ids')) {
    $selectedPackage = array_map('intval', $request->package_ids);
    $packages = Package::whereIn('id', $selectedPackage)->get();
    $total_packages = $packages->sum('package_price') ?? 0;
    }

    $coupons_data = null;
    $total_coupons = 0;
    $coupon_names = [];
    if ($request->filled('coupons')) {
        $decodedCoupons = array_map(fn($c) => json_decode($c, true), $request->coupons);
        
        foreach ($decodedCoupons as $data_coupon) {
            if (isset($data_coupon['price'])) {
                $total_coupons += (float) $data_coupon['price'];
            }
            if (isset($data_coupon['name'])) {
                $coupon_names[] = $data_coupon['name'];
            }
        }
    
        $coupons_data = json_encode($decodedCoupons);
    }
    $total = $services_total + $total_packages + $total_coupons;


    $giftCard = GiftCard::create([
        'delivery_method'   => $data['delivery_method'],
        'user_id'           => auth()->id(),
        'sender_name'       => $data['sender_name'],
        'recipient_name'    => $data['recipient_name'],
        'sender_phone'      => $data['sender_phone'],
        'recipient_phone'   => $data['recipient_phone'],
        'requested_services'=> json_encode($data['requested_services']),
        'package_ids'       => json_encode($request->package_ids) ?? null,
        'coupons'           => $coupons_data,
        'subtotal'          => $total,
    ]);
    
    return redirect()->route('cart.page')->with('success', __('messages.gift_added_success'));
}
        
}
