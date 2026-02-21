<?php

namespace App\Http\Controllers;

use App\Imports\EmployeesImport;
use App\Imports\CityImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls',
        ]);
    
        Excel::import(new ServicesImport, $request->file('file'));
    
        return back()->with('success', 'تم استيراد الخدمات بنجاح!');
    }
    public function import_staff(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls',
        ]);
    
        Excel::import(new EmployeesImport, $request->file('file'));
    
        return back()->with('success', 'تم استيراد الموظفين بنجاح!');
    }

    public function import_city(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls',
        ]);
    
        Excel::import(new CityImport, $request->file('file'));
    
        return back()->with('success', 'تم استيراد الموظفين بنجاح!');
    }

}


