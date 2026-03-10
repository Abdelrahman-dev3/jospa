<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\World\Models\State;
use Modules\Product\Models\Product;
use Modules\World\Models\City;

class BookingsController extends Controller
{
    public function salon(Request $request){
        $States = State::where('status' , 1)->get();
        $first_States = State::where('status' , 1)->first();
        $suggest = Product::with(['media' , 'categories'])->where('status', 1)->where('is_featured', 1)->where('deleted_at', null)->take(4)->get();
        return view('frontend.salon.create' , compact('States', 'suggest' , 'first_States'));
    }    
    public function home(Request $request){
        $States = State::where('status' , 1)->get();
        $suggest = Product::with(['media' , 'categories'])->where('status', 1)->where('is_featured', 1)->where('deleted_at', null)->get();
        $cities = City::where('status' , 1)->get();
        return view('frontend.home.booking.create' , compact('States', 'suggest' ,'cities'));
    }    
}
