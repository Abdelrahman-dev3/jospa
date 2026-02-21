<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vartext;

class TextController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_vartext')->only(['index']);
        $this->middleware('permission:store_vartext')->only(['store']);
    }

    public function index(){
        $banner = Vartext::where('type','banner')->first();
        $gift = Vartext::where('type','gift')->first();
        
        return view('backend.var_text.index_datatable' , compact('banner' , 'gift'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:banner,gift',
    
            'title_ar' => 'nullable|string',
            'title_en' => 'nullable|string',
    
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ]);
    
        $title = [
            'ar' => $data['title_ar'] ?? '',
            'en' => $data['title_en'] ?? '',
        ];
    
        $description = [
            'ar' => $data['description_ar'] ?? '',
            'en' => $data['description_en'] ?? '',
        ];
    
        Vartext::updateOrCreate(
            ['type' => $data['type']],
            [
                'title' => $title,
                'description' => $description,
            ]
        );
    
        return redirect()->back()->with('success', __('messages.saved_successfully'));
    }

}
