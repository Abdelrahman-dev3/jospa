<?php
 
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use Modules\Booking\Models\BookingService;
use Modules\BussinessHour\Models\BussinessHour;
use Modules\Package\Models\BookingPackages;
 
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\StaffLeavePeriod;
use App\Models\StaffWorkingHour;
use App\Models\Branch;
use Modules\World\Models\State;
use Modules\Holiday\Models\Holiday;

class BookingsController extends Controller
{
public function States()
{
    $States = State::where('status' , 1)->get();

    return response()->json($States);
}

public function branchs($id)
{
    $branches = Branch::where('status' , 1)->whereNull('deleted_by')->whereHas('address', function ($query) use ($id) {
        $query->where('state', $id);
    })
    ->with('address.state_data')
    ->get();

    return response()->json($branches);
}

public function getServiceGroups(Request $request)
{
    $is_home = (int) $request->get('is_home');

    $query = DB::table('categories')
        ->whereNull('parent_id')
        ->whereNull('deleted_at')
        ->where('status', 1);

    $query->whereExists(function ($serviceQuery) use ($is_home) {
        $serviceQuery->select(DB::raw(1))
            ->from('services')
            ->whereColumn('services.category_id', 'categories.id')
            ->whereNull('services.deleted_at')
            ->where('services.status', 1)
            ->where('services.show_in_online_booking', 1);

        if ($is_home) {
            $serviceQuery->where('services.is_visible', 1);
        } else {
            $serviceQuery->where('services.is_visible', 0);
        }
    });

    if ($is_home) {
        $query->where('is_visible', 1);
    }

    $groups = $query->get()->map(function ($item) {

        $media = DB::table('media')
            ->where('model_type', 'Modules\\Category\\Models\\Category')
            ->where('model_id', $item->id)
            ->where('collection_name', 'feature_image')
            ->first();

        $item->image = $media
            ? asset('storage/uploads/' . $media->id . '/' . $media->file_name)
            : asset('images/default.jpg');

        return $item;
    });

    return response()->json($groups);
}


public function getServicesByGroup($serviceGroupId, $branchId)
{
    if($branchId != 0){
    $branch = Branch::find($branchId);

    $services = $branch->services()
        ->where('category_id', $serviceGroupId)
        ->where('status', 1)
        ->where('show_in_online_booking', 1)
        ->where('is_visible', 0)
        ->get();
    }else{
    $services = DB::table('services')
        ->where('category_id', $serviceGroupId)
        ->where('status', 1)
        ->where('show_in_online_booking', 1)
        ->where('is_visible', 1)
        ->get();
    }
    return response()->json($services);
}

public function getstaff(Request $request)
{
    $branchId = (int) $request->get('branch_id');
    $serviceId = (int) $request->get('service_id');

    $query = DB::table('users')
        ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->join('branch_employee', 'users.id', '=', 'branch_employee.employee_id')
        ->join('service_employees', 'users.id', '=', 'service_employees.employee_id')
        ->where('roles.name', 'employee')
        ->where('model_has_roles.model_type', \App\Models\User::class)
        ->where('users.is_manager', 0)
        ->where('users.status', 1)
        ->whereNull('users.deleted_at');

    if ($branchId != 0) {
        $query->where('branch_employee.branch_id', $branchId);
    }

    if ($branchId == 0) {
        $query->where('show_in_home_booking', 1);
    }

    if ($serviceId != 0) {
        $query->where('service_employees.service_id', $serviceId);
    }

    $employees = $query->select('users.*')->get();

    return response()->json($employees);
}

public function getAvailableTimes(Request $request ,$date, $staffId)
{
    $user = User::find($staffId);
    if (!$user) {
        return response()->json([]);
    }
    try {
        $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        if ($dateObj->format('Y-m-d') !== $date) {
            return response()->json([]);
        }
    } catch (\Throwable $e) {
        return response()->json([]);
    }

    $dayName = strtolower($dateObj->format('l'));
    $serve_book_min = (int) $request->query('Increasing', 30);
    $min_minutes = max(1, $serve_book_min);

    $branchId = optional($user->branch)->branch_id;
    if (StaffLeavePeriod::where('staff_id', $staffId)
        ->whereDate('start_date', '<=', $date)
        ->whereDate('end_date', '>=', $date)
        ->exists()) {
        return response()->json([]);
    }

    if ($branchId && Holiday::where('branch_id', $branchId)->whereDate('date', $date)->exists()) {
        return response()->json([]);
    }

    $workingConfig = $this->resolveWorkingConfig($user, $staffId, $dayName);
    if (!$workingConfig) {
        return response()->json([]);
    }

    $bookedTimes = $this->buildBookedTimes($staffId, $date, $min_minutes);

    $availableTimes = $this->buildAvailableTimes(
        $date,
        $workingConfig['start_time'],
        $workingConfig['end_time'],
        $workingConfig['breaks'],
        $bookedTimes,
        $min_minutes
    );

    return response()->json($availableTimes);
}

    private function resolveWorkingConfig(User $user, int $staffId, string $dayName): ?array
    {
        $staffWorkingHours = StaffWorkingHour::where('staff_id', $staffId)
            ->where('day_of_week', $dayName)
            ->orderBy('id', 'desc')
            ->first();

        if ($staffWorkingHours) {
            if ($staffWorkingHours->is_holiday) {
                return null;
            }

            return $this->makeWorkingConfig(
                $staffWorkingHours->start_time,
                $staffWorkingHours->end_time,
                $staffWorkingHours->breaks
            );
        }

        $branchId = optional($user->branch)->branch_id;
        $shiftId = optional($user->shift)->shift_id;
        if (!$branchId) {
            return null;
        }

        $workingHours = BussinessHour::where('branch_id', $branchId)
            ->where('day', $dayName)
            ->where('is_holiday', 0)
            ->where('shift_id', $shiftId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$workingHours) {
            return null;
        }

        return $this->makeWorkingConfig(
            $workingHours->start_time,
            $workingHours->end_time,
            $workingHours->breaks
        );
    }

    private function makeWorkingConfig(?string $startTime, ?string $endTime, $breaks): ?array
    {
        try {
            $start = $this->parseTime((string) $startTime);
            $end = $this->parseTime((string) $endTime);
        } catch (\Throwable $e) {
            return null;
        }

        if ($end->lte($start)) {
            return null;
        }

        return [
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'breaks' => $this->normalizeBreaks($breaks),
        ];
    }

    private function parseTime(string $time): Carbon
    {
        $time = trim($time);
        $format = substr_count($time, ':') === 2 ? 'H:i:s' : 'H:i';

        return Carbon::createFromFormat($format, $time);
    }

    private function normalizeBreaks($breaks): array
    {
        if (is_string($breaks)) {
            $breaks = json_decode($breaks, true);
        }

        return is_array($breaks) ? $breaks : [];
    }

    private function buildAvailableTimes(string $date,string $startTime,string $endTime,array $breaks,array $bookedTimes,int $minMinutes): array {
        $availableTimes = [];
        $current = Carbon::parse($date . ' ' . $startTime, 'Asia/Riyadh');
        $end = Carbon::parse($date . ' ' . $endTime, 'Asia/Riyadh');
        $isToday = Carbon::createFromFormat('Y-m-d', $date)->isToday();
        $now = Carbon::now('Asia/Riyadh')->startOfMinute();

        while (true) {
            $candidateEnd = $current->copy()->addMinutes($minMinutes);
            if ($candidateEnd->gt($end)) {
                break;
            }

            $timeStr = $current->format('H:i');
            if ($isToday && $current->lt($now)) {
                $current->addMinute();
                continue;
            }

            if (! $this->overlapsBreaks($date, $current, $candidateEnd, $breaks) && !in_array($timeStr, $bookedTimes)) {
                $availableTimes[] = $timeStr;
            }

            $current->addMinute();
        }

        $availableTimes = $this->filterAvailableTimes($availableTimes, $minMinutes);

        return $this->filterAvailableTimesNotConf($availableTimes, $bookedTimes, $minMinutes);
    }

    private function overlapsBreaks(string $date, Carbon $current, Carbon $candidateEnd, array $breaks): bool
    {
        foreach ($breaks as $break) {
            if (!is_array($break) || empty($break['start_break']) || empty($break['end_break'])) {
                continue;
            }

            try {
                $breakStart = Carbon::parse($date . ' ' . $break['start_break'], 'Asia/Riyadh');
                $breakEnd = Carbon::parse($date . ' ' . $break['end_break'], 'Asia/Riyadh');
            } catch (\Throwable $e) {
                continue;
            }

            if ($current->lt($breakEnd) && $candidateEnd->gt($breakStart)) {
                return true;
            }
        }

        return false;
    }

    /*-----------------------Helper function to filter time---------------------------*/
    function filterAvailableTimes($availableTimes, $serviceDuration) {
        $serviceDuration = max(1, (int) $serviceDuration);
        $filtered = [];
        $count = count($availableTimes);
    
        for ($i = 0; $i < $count; ) {
            $startTime = $availableTimes[$i];
            $filtered[] = $startTime;
    
            $nextIndex = $i;
            $targetTime = Carbon::createFromFormat('H:i', $startTime)
                ->addMinutes($serviceDuration)
                ->format('H:i');
    
            while ($nextIndex < $count && $availableTimes[$nextIndex] < $targetTime) {
                $nextIndex++;
            }
    
            $i = $nextIndex;
        }
    
        return $filtered;
    }
    
    
    /*-----------------------Helper function to filter time---------------------------*/
    function filterAvailableTimesNotConf($availableTimes, $bookedTimes, $serve_book_min) {
        $serve_book_min = max(1, (int) $serve_book_min);
        $result = [];
    
        foreach ($availableTimes as $time) {
            $start = strtotime($time);
            $end   = $start + ($serve_book_min * 60);
    
            $conflict = false;
    
            foreach ($bookedTimes as $booked) {
                $bookedTimestamp = strtotime($booked);
    
                if ($bookedTimestamp >= $start && $bookedTimestamp < $end) {
                    $conflict = true;
                    break;
                }
            }
    
            if (!$conflict) {
                $result[] = $time;
            }
        }
    
        return $result;
    }

    private function buildBookedTimes(int $staffId, string $date, int $fallbackDuration): array
    {
        $serviceTimes = BookingService::where('employee_id', $staffId)
            ->whereDate('start_date_time', $date)
            ->whereHas('booking', function ($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'check_in']);
            })
            ->get(['start_date_time', 'duration_min'])
            ->flatMap(function ($booking) use ($fallbackDuration) {
                $start = Carbon::parse($booking->start_date_time);
                $duration = (int) ($booking->duration_min ?? 0);
                if ($duration < 1) {
                    $duration = $fallbackDuration;
                }

                $minutes = [];
                for ($i = 0; $i < $duration; $i++) {
                    $minutes[] = $start->copy()->addMinutes($i)->format('H:i');
                }

                return $minutes;
            })
            ->toArray();

        $packageTimes = BookingPackages::with('booking', 'services')
            ->where('employee_id', $staffId)
            ->whereHas('booking', function ($q) use ($date) {
                $q->whereDate('start_date_time', $date)
                  ->whereIn('status', ['pending', 'confirmed', 'check_in']);
            })
            ->get()
            ->flatMap(function ($package) use ($fallbackDuration) {
                $start = Carbon::parse($package->booking->start_date_time);
                $duration = (int) $package->services->sum('duration_min');
                if ($duration < 1) {
                    $duration = $fallbackDuration;
                }

                $minutes = [];
                for ($i = 0; $i < $duration; $i++) {
                    $minutes[] = $start->copy()->addMinutes($i)->format('H:i');
                }

                return $minutes;
            })
            ->toArray();

        return array_values(array_unique(array_merge($serviceTimes, $packageTimes)));
    }

}
