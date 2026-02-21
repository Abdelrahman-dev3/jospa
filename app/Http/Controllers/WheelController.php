<?php

namespace App\Http\Controllers;
use App\Models\Wheel;

use Illuminate\Http\Request;

class WheelController extends Controller
{
    public function index(){
    
        $prizes = Wheel::all();
        return view('backend.wheel.index_datatable', compact('prizes'));
        
    }

    public function store(Request $request){
    
        $request->validate([
            'gift_type' => 'required|string|max:255',
            'reward_value' => 'required|numeric|min:1',
        ], [
            'gift_type.required' => 'برجاء اختيار نوع الهدية.',
            'gift_type.string' => 'نوع الهدية يجب أن يكون نصًا.',
            'reward_value.required' => 'برجاء إدخال قيمة المكافأة.',
            'reward_value.numeric' => 'قيمة المكافأة يجب أن تكون رقمًا.',
            'reward_value.min' => 'قيمة المكافأة يجب ألا تقل عن 1.',
        ]);
        
        Wheel::create([
            'type' =>  $request->gift_type,
            'reward_value' => $request->reward_value
        ]);
        
        return redirect()->back()->with('success', __('wheel.The_award_has_been_added_successfully'));
    }
    
    public function destroy_all(){
    
        Wheel::truncate();
        
        return redirect()->back()->with('success', __('wheel.success_delete_all'));
    }
    
    public function destroy($id){
        $prize = Wheel::findOrFail($id);
        $prize->delete();
        return redirect()->back()->with('success', __('wheel.The_award_has_been_deleted_successfully'));
    }
}
 