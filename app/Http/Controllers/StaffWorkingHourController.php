<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\Booking;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StaffWorkingHourController extends Controller
{
    /**
     * عرض أوقات دوام موظف
     */
    public function index($staffId)
    {
        $staff = Staff::with('workingHours')->findOrFail($staffId);
        return view('staff_working_hours.index', compact('staff'));
    }

    /**
     * جلب الأوقات المتاحة لحجز موظف في يوم معين
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date'
        ]);

        $staffId = $request->staff_id;
        $date = $request->date;

        $dayOfWeek = Carbon::parse($date)->format('l');

        // جلب أوقات دوام الموظف في ذلك اليوم
        $workingHour = StaffWorkingHour::where('staff_id', $staffId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$workingHour) {
            return response()->json(['slots' => []]); // ما عنده دوام
        }

        // جلب الأوقات المحجوزة
        $bookedTimes = Booking::where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->pluck('time')
            ->toArray();

        // توليد أوقات متاحة بين start_time و end_time مع استبعاد المحجوز
        $availableSlots = [];

        $current = Carbon::createFromFormat('H:i:s', $workingHour->start_time);
        $endTime = Carbon::createFromFormat('H:i:s', $workingHour->end_time);

        while ($current < $endTime) {
            $timeString = $current->format('H:i:s');

            if (!in_array($timeString, $bookedTimes)) {
                $availableSlots[] = $timeString;
            }

            $current->addMinutes(30); // أو 15 دقيقة حسب النظام
        }

        return response()->json(['slots' => $availableSlots]);
    }

    /**
     * تخزين أوقات دوام جديدة
     */
    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        StaffWorkingHour::create([
            'staff_id' => $request->staff_id,
            'day_of_week' => $request->day_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return redirect()->back()->with('success', 'تم إضافة وقت الدوام بنجاح.');
    }

    /**
     * حذف وقت دوام
     */
    public function destroy($id)
    {
        $workingHour = StaffWorkingHour::findOrFail($id);
        $workingHour->delete();

        return redirect()->back()->with('success', 'تم حذف وقت الدوام.');
    }
}
