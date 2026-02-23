<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Booking\Models\Booking;
use Modules\Package\Models\BookingPackages;
use Modules\Package\Models\Package;
use Modules\Package\Models\UserPackage;

class PackageBookingController extends Controller
{
    public function storePackageBooking(Request $request)
    {
        $data = $request->validate([
            'package_id' => 'required|integer',
            'branch_id' => 'required|integer|exists:branches,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|string',
            'notes' => 'nullable|string|max:1000',
            'total_price' => 'required|numeric|min:0',
        ]);

        if (!auth()->check()) {
            session(['package_booking' => $data]);
            return response()->json(['status' => 'guest']);
        }

        return $this->completePackageBooking($data);
    }

    public function completePackageBooking(?array $data = null)
    {
        $data = $data ?? session('package_booking');

        if (!$data) {
            return redirect()->route('frontend.Packages');
        }

        DB::beginTransaction();

        try {
            $this->persistPackageBooking($data);
            DB::commit();
            session()->forget('package_booking');

            if (request()->expectsJson()) {
                return response()->json(['status' => 'saved']);
            }

            return redirect()
                ->route('frontend.Packages')
                ->with('success', 'تم حفظ الحجز بنجاح');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function persistPackageBooking(array $data): void
    {
        $booking = Booking::create([
            'status' => 'pending',
            'start_date_time' => $data['date'] . ' ' . $data['time'],
            'user_id' => auth()->id(),
            'branch_id' => $data['branch_id'],
            'note' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $package = Package::findOrFail($data['package_id']);
        $packagePrice = $package->package_price;
        $employeeId = $data['branch_id'] == 32 ? 249 : 250;

        BookingPackages::create([
            'booking_id' => $booking->id,
            'package_id' => $data['package_id'],
            'employee_id' => $employeeId,
            'user_id' => auth()->id(),
            'package_price' => $packagePrice,
            'created_by' => auth()->id(),
        ]);

        UserPackage::create([
            'booking_id' => $booking->id,
            'employee_id' => $employeeId,
            'user_id' => auth()->id(),
            'package_price' => $packagePrice,
            'purchase_date' => now(),
            'package_id' => $data['package_id'],
        ]);
    }

    public function handlePaymentResult(Request $request)
    {
        $tapId = $request->get('tap_id');

        if (!$tapId) {
            return response()->json([
                'status' => false,
                'message' => 'No tap_id provided.',
            ], 400);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TAP_SECRET_KEY'),
        ])->get("https://api.tap.company/v2/charges/{$tapId}");

        $charge = $response->json();

        if (isset($charge['status']) && $charge['status'] === 'CAPTURED') {
            return response()->json([
                'status' => true,
                'message' => 'Payment captured successfully.',
                'data' => $charge,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Payment failed or was declined.',
            'data' => $charge,
        ], 402);
    }
}
