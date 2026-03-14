<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Trait\AuthTrait;
use App\Http\Controllers\Controller;
use App\Support\AuthRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    use AuthTrait;

    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $user = $this->loginTrait($request);

        if (! $user) {
            return back()->withErrors([
                'username' => 'These credentials do not match our records.',
            ])->onlyInput('username');
        }

        if ($request->session()->has('package_booking')) {
            return redirect()->route('package.booking.complete');
        }

        if ($request->session()->has('temp_booking')) {
            $temp = $request->session()->get('temp_booking');
            $data = $temp['data'];
            $btnValue = $temp['btn_value'];

            if (method_exists($this, 'complateTempBookings')) {
                $this->complateTempBookings($data, $btnValue);
            }

            session()->forget('temp_booking');

            if ($btnValue === 'cart') {
                return redirect()->to('/cart')->with('success', 'تم تحويل الحجز بنجاح');
            }

            if ($btnValue === 'payment') {
                return redirect()->to('/payment?ids=1')->with('success', 'تم تحويل الحجز بنجاح');
            }
        }

        if ($request->get('redirect') === 'gift') {
            return redirect()->route('gift.page')->with('success', 'تم تسجيل الدخول بنجاح');
        }

        return redirect()->to(AuthRedirect::path($request->user()))
            ->with('success', 'تم تسجيل الدخول بنجاح');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/signin')->with('success', 'تم تسجيل الخروج بنجاح');
    }
}
