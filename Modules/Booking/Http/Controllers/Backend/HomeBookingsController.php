<?php

namespace Modules\Booking\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BookingHome;
use App\Models\ServiceGroupHome;
use App\Models\ServiceHome;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeBookingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view_booking'])->only(['serviceGroups', 'services', 'staff']);
        $this->middleware(['permission:add_booking'])->only('store');
    }

    public function serviceGroups(): JsonResponse
    {
        $groups = ServiceGroupHome::query()
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);

        return response()->json([
            'status' => true,
            'data' => $groups,
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_group_home_id' => ['required', 'integer', 'exists:service_group_homes,id'],
        ]);

        $services = ServiceHome::query()
            ->where('service_group_homes_id', $validated['service_group_home_id'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'duration']);

        return response()->json([
            'status' => true,
            'data' => $services,
        ]);
    }

    public function staff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_home_id' => ['required', 'integer', 'exists:service_homes,id'],
        ]);

        $staff = DB::table('staff_homes')
            ->join('staff_service_homes', 'staff_service_homes.staff_home_id', '=', 'staff_homes.id')
            ->where('staff_service_homes.service_home_id', $validated['service_home_id'])
            ->where('staff_homes.status', 'active')
            ->select('staff_homes.id', 'staff_homes.name', 'staff_homes.phone', 'staff_homes.gender')
            ->distinct()
            ->orderBy('staff_homes.name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $staff,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'mobile_no' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'service_group_home_id' => ['required', 'integer', 'exists:service_group_homes,id'],
            'service_home_id' => ['required', 'integer', 'exists:service_homes,id'],
            'staff_home_id' => ['required', 'integer', 'exists:staff_homes,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $service = ServiceHome::query()
            ->whereKey($validated['service_home_id'])
            ->where('service_group_homes_id', $validated['service_group_home_id'])
            ->where('status', 'active')
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'service_home_id' => ['الخدمة المختارة لا تنتمي إلى القسم المحدد أو غير مفعلة.'],
            ]);
        }

        $staffAssignedToService = DB::table('staff_service_homes')
            ->where('staff_home_id', $validated['staff_home_id'])
            ->where('service_home_id', $validated['service_home_id'])
            ->exists();

        if (! $staffAssignedToService) {
            throw ValidationException::withMessages([
                'staff_home_id' => ['الموظف المختار غير مسند لهذه الخدمة المنزلية.'],
            ]);
        }

        $this->ensureTimeSlotIsAvailable(
            staffHomeId: (int) $validated['staff_home_id'],
            service: $service,
            date: (string) $validated['date'],
            time: (string) $validated['time']
        );

        $booking = BookingHome::create([
            'staff_home_id' => $validated['staff_home_id'],
            'service_home_id' => $validated['service_home_id'],
            'name' => $validated['customer_name'],
            'phone' => $validated['mobile_no'],
            'address' => $validated['address'],
            'date' => $validated['date'],
            'time' => $validated['time'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الحجز المنزلي بنجاح.',
            'data' => $booking->load(['staffHome', 'serviceHome']),
        ]);
    }

    private function ensureTimeSlotIsAvailable(int $staffHomeId, ServiceHome $service, string $date, string $time): void
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}");
        $requestedEnd = (clone $requestedStart)->addMinutes($this->serviceDurationInMinutes($service));

        $existingBookings = BookingHome::query()
            ->with('serviceHome:id,duration')
            ->where('staff_home_id', $staffHomeId)
            ->whereDate('date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        foreach ($existingBookings as $existingBooking) {
            $existingStart = Carbon::createFromFormat('Y-m-d H:i:s', $existingBooking->date . ' ' . substr((string) $existingBooking->time, 0, 8));
            $existingEnd = (clone $existingStart)->addMinutes(
                $this->serviceDurationInMinutes($existingBooking->serviceHome)
            );

            if ($requestedStart < $existingEnd && $requestedEnd > $existingStart) {
                throw ValidationException::withMessages([
                    'time' => ['الموعد المختار متعارض مع حجز منزلي آخر لنفس الموظف.'],
                ]);
            }
        }
    }

    private function serviceDurationInMinutes(?ServiceHome $service): int
    {
        return max(1, (int) ($service?->duration ?? 60));
    }
}
