<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ad;

class AdController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => [
                'home'     => Ad::where('page', 'home')->get(),
            ]
        ]);
    }
}
