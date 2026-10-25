@php
    $currentRoute = Route::currentRouteName();
    $reports = [
        [
            'route' => 'backend.reports.comprehensive-report',
            'title' => __('full_report.title'),
            'icon' => 'fa-solid fa-chart-pie',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.sales-by-date',
            'title' => __('report.title_sales_report'),
            'icon' => 'fa-solid fa-chart-line',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.financial-report',
            'title' => __('report.title_financial_report'),
            'icon' => 'fa-solid fa-calculator',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.daily-booking-report',
            'title' => __('report.title_daily_report'),
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.overall-booking-report',
            'title' => __('report.title_overall_report'),
            'icon' => 'fa-solid fa-calendar-check',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.payment-transactions-report',
            'title' => __('report.title_payment_transactions_report'),
            'icon' => 'fa-solid fa-credit-card',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.order-report',
            'title' => __('report.order-report'),
            'icon' => 'fa-solid fa-box-open',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.staff-report',
            'title' => __('report.title_staff_service_report'),
            'icon' => 'fa-solid fa-clipboard-user',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.payout-report',
            'title' => __('report.title_staff_report'),
            'icon' => 'fa-solid fa-hand-holding-dollar',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.coupon-report',
            'title' => __('report.title_coupon_report'),
            'icon' => 'fa-solid fa-ticket',
            'badge' => null
        ],
        [
            'route' => 'backend.reports.promotion-report',
            'title' => __('report.title_promotion_report'),
            'icon' => 'fa-solid fa-tags',
            'badge' => null
        ],
    ];
@endphp

<div class="card mb-4 border-0 shadow-sm report-nav-card" style="border-radius: 12px; overflow: hidden; background: #fff;">
    <div class="card-body p-2" style="background: linear-gradient(135deg, #fdfbf7 0%, #ffffff 100%);">
        <div class="d-flex align-items-center justify-content-start flex-wrap gap-1 report-nav-scroll" style="overflow-x: auto; white-space: nowrap; padding-bottom: 2px;">
            @foreach($reports as $r)
                @php
                    $isActive = ($currentRoute === $r['route']);
                @endphp
                <a href="{{ route($r['route']) }}" 
                   class="btn btn-sm d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill transition-all {{ $isActive ? 'btn-primary shadow-sm text-white fw-bold' : 'btn-outline-secondary text-dark border-0' }}"
                   style="{{ $isActive ? 'background: #c39b61; border-color: #c39b61;' : 'background: rgba(0,0,0,0.03);' }} font-size: 13px;">
                    <i class="{{ $r['icon'] }} {{ $isActive ? 'text-white' : 'text-muted' }}"></i>
                    <span>{{ $r['title'] }}</span>
                </a>
            @endforeach
            <div class="ms-auto ps-2">
                @if (empty($hideExport))
                    @include('backend.reports.excel-export')
                @endif
            </div>
        </div>
    </div>
</div>
