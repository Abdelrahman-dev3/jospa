<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BooController extends Controller
{
    public function store(Request $request)
    {
        

        // إذا المستخدم مش مسجل دخول
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
                'redirect' => route('login')
            ], 401);
        }
        
        // تحقق من البيانات
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required',
            'note' => 'nullable|string|max:500',
            'payment_type' => 'nullable|in:cash,card,online',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // دمج التاريخ والوقت
            $startDateTime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $request->booking_date . ' ' . $request->booking_time
            );
            
            // التحقق من أن الموعد في المستقبل
            if ($startDateTime->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حجز موعد في الماضي'
                ], 422);
            }
            
            // التحقق من توفر الموعد (يمكنك إضافة منطق التحقق هنا)
            $existingBooking = Booking::where('branch_id', $request->branch_id)
                ->where('start_date_time', $startDateTime)
                ->where('status', '!=', 'cancelled')
                ->first();
                
            if ($existingBooking) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا الموعد محجوز مسبقاً، الرجاء اختيار وقت آخر'
                ], 422);
            }
            
            // إنشاء الحجز
            $booking = Booking::create([
                'user_id' => Auth::id(), // المستخدم المسجل دخول
                'branch_id' => $request->branch_id,
                'start_date_time' => $startDateTime,
                'note' => $request->note,
                'status' => 'pending', // أو 'confirmed' حسب نظامك
                'payment_status' => 'pending',
                'payment_type' => $request->payment_type ?? 'cash',
                'created_by' => Auth::id(), // نفس user_id
                'location' => $request->location ?? null, // إذا عندك location
            ]);
            
            // يمكنك إضافة إرسال إيميل أو إشعار هنا
            
            return response()->json([
                'success' => true,
                'message' => 'تم الحجز بنجاح! رقم حجزك: #' . $booking->id,
                'booking_id' => $booking->id,
                'booking_number' => 'BK-' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                'redirect_url' => route('frontend.bookings.show', $booking->id)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Booking Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الحجز. الرجاء المحاولة لاحقاً.'
            ], 500);
        }
    }
    
    // إذا بدك دالة لإظهار الحجز
    public function show($id)
    {
        $booking = Booking::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        return view('frontend.bookings.show', compact('booking'));
    }
}