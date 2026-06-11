@extends('backend.layouts.app')

@section('title', 'Staff Working Hours')

@section('content')
<body style="background: #f9f9f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <div style="max-width: 850px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div class="col-md-12 d-flex justify-content-between">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fa-solid fa-clock"></i> {{ __('employee.working_hours') }} </h4>
            </div>
        </div>

        <form action="{{ route('backend.staff.working-hours.store',  $userId ) }}" method="POST">
            @csrf
            


            @php
            $savedWorkingHours = App\Models\StaffWorkingHour::where('staff_id', $userId)->get()->keyBy('day_of_week');
            $leavePeriods = App\Models\StaffLeavePeriod::where('staff_id', $userId)->orderBy('start_date')->get();
                $days = [
                    1 => 'saturday',
                    2 => 'sunday',
                    3 => 'monday',
                    4 => 'tuesday',
                    5 => 'wednesday',
                    6 => 'thursday',
                    7 => 'friday',
                ];
            @endphp
            @php
                $defaultTimes = [
                    'saturday' => ['start' => '11:00', 'end' => '19:00'],
                    'sunday' => ['start' => '11:00', 'end' => '19:00'],
                    'monday' => ['start' => '11:00', 'end' => '19:00'],
                    'tuesday' => ['start' => '11:00', 'end' => '19:00'],
                    'wednesday' => ['start' => '11:00', 'end' => '19:00'],
                    'thursday' => ['start' => '11:00', 'end' => '19:00'],
                    'friday' => ['start' => '11:00', 'end' => '19:00'], 
                ];
            @endphp

            @if($savedWorkingHours->count() > 0)
            <ul class="data-scrollbar list-group list-group-flush">
    @foreach ($days as $index => $day)
        @if ($savedWorkingHours->has($day))
            @php
                $dayData   = $savedWorkingHours[$day];
                $defaultStart = $dayData->start_time;
                $defaultEnd   = $dayData->end_time;
                $isHoliday    = $dayData->is_holiday;
                $breaks       = json_decode($dayData->breaks, true) ?? [];
            @endphp

            <li class="form-group col-md-12 list-group-item">
                <div class="form-group col-md-12 gap-1">
                    <h4 class="text-capitalize">{{ $index }}. {{ __('employee.' . $day) }}</h4>

                    <div class="col-md-12 row row-cols-3">
                        {{-- وقت البدء --}}
                        <div class="flatpickr-wrapper">
                            <input class="form-control"
                                   type="time"
                                   name="working_hours[{{ $day }}][start]"
                                   value="{{ $defaultStart }}">
                        </div>

                        {{-- وقت الانتهاء --}}
                        <div class="flatpickr-wrapper">
                            <input class="form-control"
                                   type="time"
                                   name="working_hours[{{ $day }}][end]"
                                   value="{{ $defaultEnd }}">
                        </div>

                        {{-- إجازة --}}
                        <div class="form-group">
                            <div class="d-flex gap-1">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           name="working_hours[{{ $day }}][is_holiday]"
                                           id="{{ $index }}-dayoff"
                                           type="checkbox"
                                           value="1"
                                           {{ $isHoliday ? 'checked' : '' }}>
                                </div>
                                <label class="form-label" for="{{ $index }}-dayoff">{{ __('employee.add_holiday') }}</label>
                            </div>
                        </div>
                    </div>

                    {{-- عرض أوقات الراحة لو موجودة --}}
                    <div class="breaks-container" data-day="{{ $day }}">
                        @foreach ($breaks as $bIndex => $break)
                            <div class="col-md-12 row row-cols-3 align-items-end mt-2 gap-2 break-row">
                                <div class="flatpickr-wrapper">
                                    <input class="form-control" type="time" name="working_hours[{{ $day }}][breaks][{{ $bIndex }}][start_break]" value="{{ $break['start_break'] }}">
                                </div>
                                <div class="flatpickr-wrapper">
                                    <input class="form-control" type="time" name="working_hours[{{ $day }}][breaks][{{ $bIndex }}][end_break]" value="{{ $break['end_break'] }}">
                                </div>
                                <div>
                                    <a class="btn btn-danger remove-break">{{ __('employee.remove') }}</a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- زر إضافة استراحة جديدة --}}
                    <div>
                        <a class="clickable-text" data-day="{{ $day }}">
                            <h6><i class="fa fa-plus-circle" aria-hidden="true"></i> {{ __('employee.add_break') }} </h6>
                        </a>
                    </div>
                </div>
            </li>
        @endif
    @endforeach
</ul>
            @else
            <ul class="data-scrollbar list-group list-group-flush">
                @foreach ($days as $index => $day)
                    @php
                        $defaultStart = $defaultTimes[$day]['start'] ?? '09:00';
                        $defaultEnd = $defaultTimes[$day]['end'] ?? '17:00';
                    @endphp
            
                    <li class="form-group col-md-12 list-group-item">
                        <div class="form-group col-md-12 gap-1">
                            <h4 class="text-capitalize">{{ $index }}. {{ __('employee.' . $day) }}</h4>
            
                            <div class="col-md-12 row row-cols-3">
                                {{-- وقت البدء --}}
                                <div class="flatpickr-wrapper">
                                    <input class="form-control"
                                           type="time"
                                           name="working_hours[{{ $day }}][start]"
                                           value="{{ old('working_hours.' . $day . '.start', $defaultStart) }}">
                                </div>
            
                                {{-- وقت الانتهاء --}}
                                <div class="flatpickr-wrapper">
                                    <input class="form-control"
                                           type="time"
                                           name="working_hours[{{ $day }}][end]"
                                           value="{{ old('working_hours.' . $day . '.end', $defaultEnd) }}">
                                </div>
            
                                {{-- إجازة --}}
                                <div class="form-group">
                                    <div class="d-flex gap-1">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   name="working_hours[{{ $day }}][is_holiday]"
                                                   id="{{ $index }}-dayoff"
                                                   type="checkbox"
                                                   value="1">
                                        </div>
                                        <label class="form-label" for="{{ $index }}-dayoff">{{ __('employee.add_holiday') }} </label>
                                    </div>
                                </div>
                            </div>
            
                            {{-- إضافة استراحة --}}
                            <div>
                                <a class="clickable-text" data-day="{{ $day }}">
                                    <h6><i class="fa fa-plus-circle" aria-hidden="true"></i> {{ __('employee.add_break') }} </h6>
                                </a>
                            </div>
            
                            {{-- مكان الاستراحات المضافة --}}
                            <div class="breaks-container" data-day="{{ $day }}"></div>
                        </div>
                    </li>
                @endforeach
</ul>
            @endif

            <div class="mt-4 p-3 border rounded">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fa-solid fa-calendar-days"></i> إجازات الموظف المحددة بتاريخ</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-leave-period">
                        <i class="fa fa-plus-circle" aria-hidden="true"></i> إضافة إجازة
                    </button>
                </div>

                @if($leavePeriods->count() > 0)
                    <div class="mb-3">
                        @foreach($leavePeriods as $leave)
                            <div class="row align-items-center g-2 mb-2">
                                <div class="col-md-3">
                                    <input class="form-control" type="date" value="{{ $leave->start_date->format('Y-m-d') }}" disabled>
                                </div>
                                <div class="col-md-3">
                                    <input class="form-control" type="date" value="{{ $leave->end_date->format('Y-m-d') }}" disabled>
                                </div>
                                <div class="col-md-4">
                                    <input class="form-control" type="text" value="{{ $leave->reason }}" disabled>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delete_leave_periods[]" value="{{ $leave->id }}" id="delete-leave-{{ $leave->id }}">
                                        <label class="form-check-label" for="delete-leave-{{ $leave->id }}">حذف</label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div id="leave-periods-container"></div>
            </div>

                <div data-v-50fdd42d="" class="d-grid d-md-block setting-footer"><button data-v-50fdd42d="" class="btn btn-primary" name="submit"><i data-v-50fdd42d="" class="fa-solid fa-floppy-disk"></i> {{ __('employee.submit') }}</button></div>
        </form>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const addBreakLinks = document.querySelectorAll(".clickable-text");
    const addLeaveButton = document.getElementById("add-leave-period");
    const leavePeriodsContainer = document.getElementById("leave-periods-container");

    const reindexBreakRows = (day) => {
        const breaksContainer = document.querySelector(`.breaks-container[data-day="${day}"]`);

        if (!breaksContainer) {
            return;
        }

        breaksContainer.querySelectorAll(".break-row").forEach((row, index) => {
            const startInput = row.querySelector('input[name*="[start_break]"]');
            const endInput = row.querySelector('input[name*="[end_break]"]');

            if (startInput) {
                startInput.name = `working_hours[${day}][breaks][${index}][start_break]`;
            }

            if (endInput) {
                endInput.name = `working_hours[${day}][breaks][${index}][end_break]`;
            }
        });
    };

    document.addEventListener("click", function (event) {
        const removeButton = event.target.closest(".remove-break");

        if (!removeButton) {
            return;
        }

        event.preventDefault();

        const breakRow = removeButton.closest(".break-row");
        const breaksContainer = removeButton.closest(".breaks-container");

        if (!breakRow || !breaksContainer) {
            return;
        }

        const day = breaksContainer.getAttribute("data-day");
        breakRow.remove();
        reindexBreakRows(day);
    });

    addBreakLinks.forEach((link) => {
        link.addEventListener("click", function (event) {
            event.preventDefault();

            const day = link.getAttribute("data-day");
            const breaksContainer = document.querySelector(`.breaks-container[data-day="${day}"]`);

            reindexBreakRows(day);
            const breakIndex = breaksContainer.querySelectorAll(".break-row").length;

            const breakRow = document.createElement("div");
            breakRow.classList.add("col-md-12", "row", "row-cols-3", "align-items-end", "mt-2", "gap-2", "break-row");

            breakRow.innerHTML = `
                <div class="flatpickr-wrapper">
                    <input class="form-control" value="09:00" type="time" name="working_hours[${day}][breaks][${breakIndex}][start_break]">
                </div>
                <div class="flatpickr-wrapper">
                    <input class="form-control" value="17:00" type="time" name="working_hours[${day}][breaks][${breakIndex}][end_break]">
                </div>
                <div>
                    <a class="btn btn-danger remove-break">{{ __('employee.remove') }}</a>
                </div>
            `;

            breaksContainer.appendChild(breakRow);
        });
    });

    if (addLeaveButton && leavePeriodsContainer) {
        addLeaveButton.addEventListener("click", function () {
            const leaveIndex = leavePeriodsContainer.querySelectorAll(".leave-period-row").length;
            const leaveRow = document.createElement("div");
            leaveRow.classList.add("row", "align-items-end", "g-2", "mb-2", "leave-period-row");

            leaveRow.innerHTML = `
                <div class="col-md-3">
                    <label class="form-label">من تاريخ</label>
                    <input class="form-control" type="date" name="leave_periods[${leaveIndex}][start_date]" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">إلى تاريخ</label>
                    <input class="form-control" type="date" name="leave_periods[${leaveIndex}][end_date]" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">السبب</label>
                    <input class="form-control" type="text" name="leave_periods[${leaveIndex}][reason]" maxlength="255">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger remove-leave-period">{{ __('employee.remove') }}</button>
                </div>
            `;

            leavePeriodsContainer.appendChild(leaveRow);

            leaveRow.querySelector(".remove-leave-period").addEventListener("click", function () {
                leaveRow.remove();
            });
        });
    }
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    @if(session('success'))
        toastr.success("{{ session('success') }}");
    @endif

    @if(session('error'))
        toastr.error("{{ session('error') }}");
    @endif

    @if(session('info'))
        toastr.info("{{ session('info') }}");
    @endif

    @if(session('warning'))
        toastr.warning("{{ session('warning') }}");
    @endif
</script>


</body>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

@endpush

@push('scripts')
@endpush
