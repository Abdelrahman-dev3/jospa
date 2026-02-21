<?php

namespace Modules\Tracking\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\Tracking\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Modules\Affiliate\Models\Affiliate;

class TrackingController extends Controller
{
    public function track(Request $request, $ref_code, $id = null)
    {
        $affiliate = Affiliate::where('ref_code', $ref_code)->first();

        if (!$affiliate) {
            return redirect('/');
        }

        $token = session('affiliate_token');

        if (!$token) {
            $token = Str::uuid()->toString();
            session(['affiliate_token' => $token]);
        }

        Visitor::firstOrCreate(
            ['token' => $token],
            [
                'affiliate_id' => $affiliate->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return view('tracking::redirect', [
            'token' => $token,
            'redirectTo' => $id ? url('/product/' . $id) : url('/'),
        ]);
    }
}
