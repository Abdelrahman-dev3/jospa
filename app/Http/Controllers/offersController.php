<?php

namespace App\Http\Controllers;
use App\Models\Ouroffersection;
use Illuminate\Http\Request;

class offersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_offers')->only('index');
        $this->middleware('permission:store_offers')->only('store');
    }
    
        public function index(Request $request)
    {
        $module_action = 'List';
        $module_title = 'Offers Cards';
        
        return view('backend.offers.index_datatable', compact('module_action', 'module_title'));
    }
        public function store(Request $request)
    {
        $request->validate([
            'discount_type'   => 'required|in:percentage,fixed',
            'discount_value'  => 'required|numeric|min:1',
            'description.ar'  => 'required|string',
            'description.en'  => 'required|string',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'color'           => 'nullable|string',
            'link'            => 'nullable|url',
            'overlay'         => 'nullable|boolean',
        ]);
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'user_image_' . time() . '.' . $image->getClientOriginalExtension();
            // المسار الصحيح داخل public_html
            $destinationPath = '/home/city2tec/public_html/offers_images';
            // تأكد إنو المجلد موجود
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0775, true);
            }
            // نقل الصورة
            $image->move($destinationPath, $imageName);
            // خزن المسار النسبي بالنسبة للموقع
            $imagePath = 'offers_images/' . $imageName;
        }
                Ouroffersection::create([
            'discount_type'  => $request->discount_type,
            'discount_value' => $request->discount_value,
            'description'    => [
                'ar' => $request->description['ar'],
                'en' => $request->description['en'],
            ],
            'start_date'     => $request->start_date,
            'end_date'       => $request->end_date,
            'color'          => $request->color,
            'image'          => $imagePath, 
            'link'           => $request->link,
            'overlay'        => $request->boolean('overlay'),
        ]);
        
            return redirect()->route('app.offers')->with('success', 'تم حفظ العرض بنجاح');
    }

}
