@extends('backend.layouts.app')

@section('title') سجل الحجز #{{ $booking->id }} @endsection

@section('banner-button')
    <a href="{{ route('backend.bookings.index', ['booking_id' => $booking->id]) }}" class="btn btn-soft-primary">
        <i class="fa-solid fa-calendar-days me-1"></i> عرض في التقويم
    </a>
    <a href="{{ route('backend.bookings.datatable_view') }}" class="btn btn-soft-secondary">
        <i class="fa-solid fa-table me-1"></i> قائمة الحجوزات
    </a>
@endsection

@section('content')
<div class="container-fluid" dir="rtl">

    {{-- ===== Page Header ===== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>
                سجل نشاطات وحركات الحجز
            </h4>
            <p class="text-muted mb-0 small">
                تتبّع كامل لكل ما جرى على هذا الحجز منذ إنشائه حتى الآن
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-dark fs-6 px-3 py-2">
                إجمالي الحركات: {{ count($activities) }}
            </span>
            <button onclick="window.location.reload()" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-rotate me-1"></i> تحديث
            </button>
        </div>
    </div>

    <div class="row g-4">

        {{-- ===== Left Column: Booking Info ===== --}}
        <div class="col-xl-4 col-lg-5">

            {{-- Booking Summary Card --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-calendar-check fa-lg"></i>
                        <h6 class="mb-0 fw-bold">ملخّص الحجز #{{ $booking->id }}</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        {{-- Status --}}
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <span class="text-muted small fw-semibold">الحالة الحالية</span>
                            <span class="badge bg-{{ $summary['status_color'] }} px-3 py-2">
                                <i class="{{ $summary['status_icon'] }} me-1"></i>
                                {{ $summary['status_label'] }}
                            </span>
                        </li>

                        @if($summary['is_deleted'])
                        <li class="list-group-item bg-danger-subtle py-3 px-4">
                            <div class="d-flex align-items-center gap-2 text-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <strong>هذا الحجز تم حذفه نهائياً</strong>
                            </div>
                        </li>
                        @endif

                        {{-- Customer --}}
                        <li class="list-group-item py-3 px-4">
                            <div class="d-flex align-items-center gap-3">
                                <img src="{{ $booking->user->profile_image ?? default_user_avatar() }}"
                                    class="avatar avatar-45 rounded-circle border" alt="avatar"
                                    onerror="this.src='{{ default_user_avatar() }}'">
                                <div>
                                    <div class="fw-bold">{{ $summary['customer_name'] }}</div>
                                    <small class="text-muted">
                                        <i class="fa-solid fa-phone me-1"></i>{{ $summary['customer_phone'] }}
                                    </small>
                                </div>
                            </div>
                        </li>

                        {{-- Branch --}}
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <span class="text-muted small">
                                <i class="fa-solid fa-store me-1 text-primary"></i> الفرع
                            </span>
                            <span class="fw-semibold">{{ $summary['branch_name'] }}</span>
                        </li>

                        {{-- Datetime --}}
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <span class="text-muted small">
                                <i class="fa-solid fa-clock me-1 text-primary"></i> موعد الحجز
                            </span>
                            <span class="fw-semibold small">
                                {{ $booking->start_date_time ? \Carbon\Carbon::parse($booking->start_date_time)->translatedFormat('d F Y - h:i A') : '-' }}
                            </span>
                        </li>

                        {{-- Services --}}
                        @if($booking->services->isNotEmpty())
                        <li class="list-group-item py-3 px-4">
                            <div class="text-muted small mb-2">
                                <i class="fa-solid fa-spa me-1 text-primary"></i> الخدمات المحجوزة
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($booking->services as $srv)
                                    <span class="badge bg-soft-primary border border-primary-subtle text-primary px-2">
                                        {{ $srv->service_name ?? ($srv->service->name ?? 'خدمة') }}
                                    </span>
                                @endforeach
                            </div>
                        </li>
                        @endif

                        {{-- Payment Status --}}
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <span class="text-muted small">
                                <i class="fa-solid fa-credit-card me-1 text-primary"></i> حالة الدفع
                            </span>
                            @if($booking->payment_status == 1)
                                <span class="badge bg-success px-2 py-1">
                                    <i class="fa-solid fa-check me-1"></i> مدفوع
                                </span>
                            @else
                                <span class="badge bg-warning text-dark px-2 py-1">
                                    <i class="fa-solid fa-clock me-1"></i> غير مدفوع
                                </span>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Audit Trail Quick Stats --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-users me-2 text-primary"></i> المستخدمون المرتبطون</h6>
                </div>
                <div class="card-body">
                    {{-- Created By --}}
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <img src="{{ $summary['created_by']['avatar'] }}" class="avatar avatar-40 rounded-circle border flex-shrink-0"
                            onerror="this.src='{{ default_user_avatar() }}'">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <small class="text-muted">أنشأ الحجز</small>
                                <span class="{{ $summary['created_by']['role_badge_class'] }}" style="font-size:0.7rem">{{ $summary['created_by']['role'] }}</span>
                            </div>
                            <div class="fw-bold small text-truncate">{{ $summary['created_by']['name'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem">
                                <i class="fa-solid fa-clock me-1"></i>{{ $summary['created_at'] }}
                                <span class="ms-1 badge bg-light text-secondary">({{ $summary['created_time_ago'] }})</span>
                            </div>
                        </div>
                    </div>

                    {{-- Last Updated By --}}
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <img src="{{ $summary['updated_by']['avatar'] }}" class="avatar avatar-40 rounded-circle border flex-shrink-0"
                            onerror="this.src='{{ default_user_avatar() }}'">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <small class="text-muted">آخر تعديل بواسطة</small>
                                <span class="{{ $summary['updated_by']['role_badge_class'] }}" style="font-size:0.7rem">{{ $summary['updated_by']['role'] }}</span>
                            </div>
                            <div class="fw-bold small text-truncate">{{ $summary['updated_by']['name'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem">
                                <i class="fa-solid fa-clock me-1"></i>{{ $summary['updated_at'] }}
                                <span class="ms-1 badge bg-light text-secondary">({{ $summary['updated_time_ago'] }})</span>
                            </div>
                        </div>
                    </div>

                    @if($summary['is_deleted'] && $summary['deleted_by'])
                    {{-- Deleted By --}}
                    <div class="d-flex align-items-center gap-3">
                        <img src="{{ $summary['deleted_by']['avatar'] }}" class="avatar avatar-40 rounded-circle border border-danger flex-shrink-0"
                            onerror="this.src='{{ default_user_avatar() }}'">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <small class="text-danger fw-semibold">حذفه من النظام</small>
                                <span class="badge bg-danger" style="font-size:0.7rem">{{ $summary['deleted_by']['role'] }}</span>
                            </div>
                            <div class="fw-bold small text-truncate text-danger">{{ $summary['deleted_by']['name'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem">
                                <i class="fa-solid fa-clock me-1"></i>{{ $summary['deleted_at'] }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Activity Breakdown Stats --}}
            @php
                $createdCount = collect($activities)->where('event', 'created')->count();
                $statusCount = collect($activities)->where('event', 'status_changed')->count();
                $updatedCount = collect($activities)->where('event', 'updated')->count();
                $deletedCount = collect($activities)->whereIn('event', ['deleted', 'restored'])->count();
                $paymentCount = collect($activities)->where('event', 'payment_updated')->count();
            @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-chart-pie me-2 text-primary"></i> إحصاءات السجل</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 rounded bg-success-subtle">
                                <div class="fw-bold fs-5 text-success">{{ $createdCount }}</div>
                                <div class="text-muted" style="font-size:0.72rem">إنشاء</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-info-subtle">
                                <div class="fw-bold fs-5 text-info">{{ $statusCount }}</div>
                                <div class="text-muted" style="font-size:0.72rem">تغيير حالة</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-warning-subtle">
                                <div class="fw-bold fs-5 text-warning">{{ $updatedCount }}</div>
                                <div class="text-muted" style="font-size:0.72rem">تعديل</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-danger-subtle">
                                <div class="fw-bold fs-5 text-danger">{{ $deletedCount }}</div>
                                <div class="text-muted" style="font-size:0.72rem">حذف</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-primary-subtle">
                                <div class="fw-bold fs-5 text-primary">{{ $paymentCount }}</div>
                                <div class="text-muted" style="font-size:0.72rem">مدفوعات</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- end left col --}}

        {{-- ===== Right Column: Timeline ===== --}}
        <div class="col-xl-8 col-lg-7">

            {{-- Filter Bar --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3 px-4">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-muted small fw-semibold me-1">
                            <i class="fa-solid fa-filter me-1"></i> تصفية:
                        </span>
                        <button class="btn btn-sm btn-dark active filter-btn" data-filter="all">
                            الكل ({{ count($activities) }})
                        </button>
                        @if($createdCount)
                        <button class="btn btn-sm btn-outline-success filter-btn" data-filter="created">
                            <i class="fa-solid fa-plus-circle me-1"></i> إنشاء ({{ $createdCount }})
                        </button>
                        @endif
                        @if($statusCount)
                        <button class="btn btn-sm btn-outline-info filter-btn" data-filter="status_changed">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> تغيير الحالة ({{ $statusCount }})
                        </button>
                        @endif
                        @if($updatedCount)
                        <button class="btn btn-sm btn-outline-warning filter-btn" data-filter="updated">
                            <i class="fa-solid fa-pen-to-square me-1"></i> تعديلات ({{ $updatedCount }})
                        </button>
                        @endif
                        @if($deletedCount)
                        <button class="btn btn-sm btn-outline-danger filter-btn" data-filter="deleted">
                            <i class="fa-solid fa-trash me-1"></i> حذف ({{ $deletedCount }})
                        </button>
                        @endif
                        @if($paymentCount)
                        <button class="btn btn-sm btn-outline-primary filter-btn" data-filter="payment_updated">
                            <i class="fa-solid fa-credit-card me-1"></i> مدفوعات ({{ $paymentCount }})
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Timeline --}}
            @if(empty($activities))
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fa-solid fa-circle-info fa-3x text-info mb-3"></i>
                        <h5 class="fw-bold">لا توجد سجلات نشاط بعد</h5>
                        <p class="text-muted">سيتم تسجيل كل تغيير يجري على هذا الحجز هنا تلقائياً</p>
                        <a href="{{ route('backend.bookings.index', ['booking_id' => $booking->id]) }}" class="btn btn-primary mt-2">
                            <i class="fa-solid fa-calendar-check me-1"></i> فتح الحجز
                        </a>
                    </div>
                </div>
            @else
                <div id="activity-timeline">
                    @foreach($activities as $activity)
                        @php
                            $filterCat = match($activity['event']) {
                                'created' => 'created',
                                'status_changed' => 'status_changed',
                                'updated' => 'updated',
                                'deleted', 'restored' => 'deleted',
                                'payment_updated' => 'payment_updated',
                                default => 'other'
                            };
                        @endphp
                        <div class="activity-card mb-3" data-category="{{ $filterCat }}">
                            <div class="card border-0 shadow-sm" style="border-right: 4px solid var(--bs-{{ $activity['badge_color'] }}) !important;">
                                {{-- Card Header --}}
                                <div class="card-header bg-{{ $activity['badge_color'] }}-subtle border-0 py-2 px-4">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-{{ $activity['badge_color'] }} px-2 py-1">
                                                <i class="{{ $activity['icon'] }} me-1"></i>{{ $activity['event_badge'] }}
                                            </span>
                                            <span class="fw-semibold text-dark small">{{ $activity['description'] }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="fa-regular fa-clock text-muted small"></i>
                                            <span class="text-muted small" title="{{ $activity['created_at'] }}">
                                                {{ $activity['formatted_date'] }}
                                            </span>
                                            <span class="badge bg-light text-secondary ms-1 small">({{ $activity['time_ago'] }})</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Card Body --}}
                                <div class="card-body px-4 py-3">
                                    {{-- Who Did It --}}
                                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $activity['causer']['avatar'] }}" alt="avatar"
                                                class="avatar avatar-32 rounded-circle border"
                                                onerror="this.src='{{ default_user_avatar() }}'">
                                            <div>
                                                <span class="fw-semibold small">{{ $activity['causer']['name'] }}</span>
                                                <span class="{{ $activity['causer']['role_badge_class'] }} ms-1" style="font-size:0.7rem">{{ $activity['causer']['role'] }}</span>
                                                @if(!empty($activity['causer']['mobile']) && $activity['causer']['mobile'] !== '-')
                                                    <small class="text-muted ms-2">
                                                        <i class="fa-solid fa-phone me-1"></i>{{ $activity['causer']['mobile'] }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                        @if(!empty($activity['properties']['ip_address']))
                                            <div class="text-muted" style="font-size:0.72rem">
                                                <i class="fa-solid fa-network-wired me-1"></i>{{ $activity['properties']['ip_address'] }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Event-specific content --}}
                                    @if($activity['event'] === 'status_changed')
                                        <div class="row g-3 align-items-center">
                                            <div class="col text-center">
                                                <div class="small text-muted mb-1">الحالة السابقة</div>
                                                <span class="badge bg-secondary px-3 py-2 fs-6">
                                                    {{ $activity['properties']['old_status_label'] ?? '-' }}
                                                </span>
                                            </div>
                                            <div class="col-auto text-primary fs-4">
                                                <i class="fa-solid fa-arrow-left"></i>
                                            </div>
                                            <div class="col text-center">
                                                <div class="small text-muted mb-1">الحالة الجديدة</div>
                                                <span class="badge bg-{{ $activity['badge_color'] }} px-3 py-2 fs-6">
                                                    {{ $activity['properties']['new_status_label'] ?? '-' }}
                                                </span>
                                            </div>
                                        </div>
                                        @if(!empty($activity['properties']['reason']))
                                            <div class="mt-3 p-2 rounded bg-light-subtle border small text-muted">
                                                <strong>السبب:</strong> {{ $activity['properties']['reason'] }}
                                            </div>
                                        @endif

                                    @elseif($activity['event'] === 'created')
                                        <div class="row g-2 small">
                                            @if(!empty($activity['properties']['start_date_time']))
                                            <div class="col-md-6">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <i class="fa-solid fa-clock me-1 text-primary"></i>
                                                    <strong>الموعد:</strong> {{ $activity['properties']['start_date_time'] }}
                                                </div>
                                            </div>
                                            @endif
                                            @if(!empty($activity['properties']['branch_name']))
                                            <div class="col-md-6">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <i class="fa-solid fa-store me-1 text-primary"></i>
                                                    <strong>الفرع:</strong> {{ $activity['properties']['branch_name'] }}
                                                </div>
                                            </div>
                                            @endif
                                            @if(!empty($activity['properties']['services']))
                                            <div class="col-12">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <i class="fa-solid fa-spa me-1 text-primary"></i>
                                                    <strong>الخدمات:</strong>
                                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                                        @foreach($activity['properties']['services'] as $srv)
                                                            <span class="badge bg-soft-primary border">
                                                                {{ $srv['name'] ?? 'خدمة' }}
                                                                @if(!empty($srv['employee']))
                                                                    <small class="opacity-75">({{ $srv['employee'] }})</small>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            @elseif(!empty($activity['properties']['services_summary']))
                                            <div class="col-12">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <i class="fa-solid fa-spa me-1 text-primary"></i>
                                                    <strong>الخدمات:</strong> {{ $activity['properties']['services_summary'] }}
                                                </div>
                                            </div>
                                            @endif
                                        </div>

                                    @elseif($activity['event'] === 'updated' && !empty($activity['changes']))
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 align-middle small">
                                                <thead class="table-light">
                                                    <tr class="text-center">
                                                        <th class="text-start" style="width:28%">العنصر</th>
                                                        <th class="text-danger" style="width:36%">
                                                            <i class="fa-solid fa-circle-xmark me-1"></i>القيمة القديمة
                                                        </th>
                                                        <th class="text-success" style="width:36%">
                                                            <i class="fa-solid fa-circle-check me-1"></i>القيمة الجديدة
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($activity['changes'] as $change)
                                                        <tr>
                                                            <td class="fw-bold text-dark">
                                                                <i class="fa-solid fa-circle-dot me-1 text-warning" style="font-size:0.6rem"></i>
                                                                {{ $change['label'] ?? $change['key'] }}
                                                            </td>
                                                            <td class="bg-danger-subtle text-danger text-wrap">
                                                                <del>{{ $change['old'] ?? '-' }}</del>
                                                            </td>
                                                            <td class="bg-success-subtle text-success text-wrap fw-semibold">
                                                                {{ $change['new'] ?? '-' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                    @elseif($activity['event'] === 'payment_updated')
                                        <div class="d-flex align-items-center gap-4 p-3 rounded bg-light-subtle border small">
                                            <div>
                                                <div class="text-muted mb-1">حالة الدفع الجديدة</div>
                                                <span class="badge bg-{{ ($activity['properties']['new_status'] ?? 0) == 1 ? 'success' : 'warning text-dark' }} px-3 py-2 fs-6">
                                                    {{ $activity['properties']['new_status_label'] ?? '-' }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="text-muted mb-1">طريقة الدفع</div>
                                                <span class="badge bg-soft-info border border-info-subtle text-info px-3 py-2">
                                                    {{ strtoupper($activity['properties']['transaction_type'] ?? 'cash') }}
                                                </span>
                                            </div>
                                        </div>

                                    @elseif($activity['event'] === 'deleted')
                                        <div class="alert alert-danger mb-0 d-flex align-items-start gap-2 py-2">
                                            <i class="fa-solid fa-triangle-exclamation fa-lg mt-1"></i>
                                            <div>
                                                <strong>تم حذف هذا الحجز نهائياً.</strong>
                                                @if(!empty($activity['properties']['reason']))
                                                    <div class="mt-1 small">السبب: {{ $activity['properties']['reason'] }}</div>
                                                @endif
                                            </div>
                                        </div>

                                    @elseif($activity['event'] === 'restored')
                                        <div class="alert alert-success mb-0 d-flex align-items-start gap-2 py-2">
                                            <i class="fa-solid fa-circle-check fa-lg mt-1"></i>
                                            <strong>تم استرجاع الحجز بنجاح وإعادته إلى قائمة الحجوزات النشطة.</strong>
                                        </div>

                                    @else
                                        <div class="text-muted small">{{ $activity['description'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Footer --}}
            <div class="text-center text-muted small mt-4 pt-2 border-top">
                <i class="fa-solid fa-shield-halved me-1 text-primary"></i>
                نظام تدقيق وسجل الحجوزات الآمن — JOSPA Audit Log System
            </div>
        </div>{{-- end right col --}}

    </div>{{-- end row --}}
</div>

<style>
    .activity-card .card {
        border-right-width: 4px !important;
        border-right-style: solid !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .activity-card .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1) !important;
    }
    .filter-btn.active {
        opacity: 1;
    }
    .filter-btn:not(.active) {
        opacity: 0.65;
    }
</style>
@endsection

@push('after-scripts')
<script>
    // Filter logic
    document.addEventListener('DOMContentLoaded', function () {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const cards = document.querySelectorAll('.activity-card');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active', 'btn-dark'));
                filterBtns.forEach(b => {
                    if (!b.classList.contains('btn-dark')) {
                        // keep outline classes
                    }
                });
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');
                let visibleCount = 0;

                cards.forEach(card => {
                    const cat = card.getAttribute('data-category');
                    if (filter === 'all' || cat === filter) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    });
</script>
@endpush
