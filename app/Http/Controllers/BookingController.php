<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'customer_name' => 'required|string|max:255',
                'mobile_no' => 'required|string|max:20',
                'neighborhood' => 'required|string|max:255',
                'gender' => 'required|in:men,women',
                'service_group_id' => 'required|exists:service_group_homes,id',
                'service_id' => 'required|exists:service_homes,id',
                'date' => 'required|date',
                'time' => 'required|string',
                'branch' => 'required|exists:branches,id',
                'staff_id' => 'required|exists:staff_homes,id',
            ]);

            $booking = new Booking();
            $booking->note = 'Customer: ' . $data['customer_name'] . ', Mobile: ' . $data['mobile_no']
                . ', Neighborhood: ' . $data['neighborhood'] . ', Gender: ' . $data['gender'];
            $booking->status = 'pending';
            $booking->start_date_time = $data['date'] . ' ' . $data['time'];
            $booking->user_id = $data['staff_id'];
            $booking->branch_id = $data['branch'];
            $booking->created_by = 1;
            $booking->save();

            return response()->json(['message' => 'Booking saved successfully']);
        } catch (\Exception $e) {
            Log::error('Booking Store Error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ الحجز',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
