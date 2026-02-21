<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class LoyaltyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_loyalty')->only('index');
        $this->middleware('permission:store_loyalty')->only('store');
    }
    
    public function index(){
        $points_per_100 = Setting::get('points_per_100');
        $point_value = Setting::get('point_value');
        return view('backend.loyalty.index' , compact('points_per_100','point_value'));
    }

    public function store(Request $request){
        $request->validate([
            'points_per_100' => 'required|numeric|min:1',
            'point_value'    => 'required|numeric|min:0.01',
        ]);
        Setting::set('points_per_100', $request->points_per_100);
        Setting::set('point_value', $request->point_value);
    
        return redirect()->back()->with('success', __('messages.success_save_loyalty'));
    }
    
    public function loyalety(Request $request){
        $point_value = Setting::get('point_value');
        
        return view('components.frontend.loyalety' , compact('point_value'));
    }
}
