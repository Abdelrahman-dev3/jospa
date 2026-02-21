<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vartext;

class VartextController extends Controller
{
    public function index()
    {
        $banner = Vartext::where('type', 'banner')->first();
        $gift   = Vartext::where('type', 'gift')->first();
    
        return response()->json([
            'status' => true,
            'data' => [
                'banner' => $banner,
                'gift'   => $gift,
            ]
        ]);
    }
}
