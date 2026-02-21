<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Term;

class TermsAndConditionsController extends Controller
{
    public function index(){
        $term = Term::all();

        return view('backend.TermsAndConditions.index_datatable');
    }

    public function store(Request $request){
        $request->validate([
            'title_ar' => 'required|max:255',
            'title_en' => 'required|max:255',
            'points'   => 'required|array',
        ]);
        
         $title = [
            'ar' => $request->input('title_ar'),
            'en' => $request->input('title_en'),
        ];
        $points = [];
        foreach ($request->points as $point) {
            $points['ar'][] = $point['ar'];
            $points['en'][] = $point['en'];
        }
        $term = Term::create([
            'title'     => $title,
            'points'    => $points,
        ]);
        return redirect()->back()->with('success', 'تم حفظ السياسة بنجاح ✅');
    }
    
    public function destroy($id){
        $term = Term::findOrFail($id);
        $term->delete();
        return redirect()->back()->with('success', 'تم حذف السياسة بنجاح ✅');
    }
    
    public function update(Request $request, $id){
        $request->validate([
            'title_ar' => 'required|max:255',
            'title_en' => 'required|max:255',
            'points'   => 'required|array',
        ]);
         $title = [
            'ar' => $request->input('title_ar'),
            'en' => $request->input('title_en'),
        ];
        $points = [];
        foreach ($request->points as $point) {
            $points['ar'][] = $point['ar'];
            $points['en'][] = $point['en'];
        }
        $term = Term::findOrFail($id);
        $term->update([
            'title'     => $title,
            'points'    => $points,
        ]);
        return redirect()->back()->with('success', 'تم حفظ السياسة بنجاح ✅');
    }
}
