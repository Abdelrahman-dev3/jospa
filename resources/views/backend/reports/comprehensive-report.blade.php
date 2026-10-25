@extends('backend.layouts.app')

@section('title') {{ __('full_report.title') }} @endsection

@push('after-styles')
<style>
    .fr-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; height: 100%; }
    .fr-kpi small { color: #64748b; display: block; margin-bottom: 4px; }
    .fr-kpi strong { font-size: 21px; color: #0f172a; }
    .fr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; margin-bottom: 18px; }
    .fr-card h5 { font-size: 16px; font-weight: 800; margin-bottom: 14px; }
    .fr-card h5 i { color: #c39b61; margin-inline-end: 6px; }
    .fr-table td, .fr-table th { padding: 7px 8px; font-size: 13px; }
    .fr-note { color: #64748b; font-size: 12px; }
    @media print {
        .report-nav-card, .fr-filter, .iq-top-navbar, .iq-navbar-header, .sidebar, .no-print, footer { display: none !important; }
        .fr-card, .fr-kpi { break-inside: avoid; box-shadow: none; }
        .content-inner { margin: 0 !important; padding: 0 !important; }
    }
</style>
@endpush

@section('content')
@include('backend.reports.report-nav', ['hideExport' => true])

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $statusLabels = [
        'pending' => __('full_report.st_pending'), 'confirmed' => __('full_report.st_confirmed'),
        'check_in' => __('full_report.st_check_in'), 'checkout' => __('full_report.st_checkout'), 'check_out' => __('full_report.st_checkout'),
        'completed' => __('full_report.st_completed'), 'cancelled' => __('full_report.st_cancelled'), 'canceled' => __('full_report.st_cancelled'),
    ];
    $attemptLabels = [
        'paid' => __('full_report.pa_paid'), 'failed' => __('full_report.pa_failed'), 'cancelled' => __('full_report.pa_cancelled'),
        'pending' => __('full_report.pa_pending'), 'initiated' => __('full_report.pa_initiated'),
    ];
    $loyaltyLabels = ['add' => __('full_report.ly_add'), 'deduct' => __('full_report.ly_deduct'), 'redeem' => __('full_report.ly_deduct'), 'subtract' => __('full_report.ly_deduct')];
@endphp

<form method="GET" class="fr-card fr-filter">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">{{ __('full_report.from') }}</label><input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
        <div class="col-md-2"><label class="form-label">{{ __('full_report.to') }}</label><input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
        <div class="col-md-3">
            <label class="form-label">{{ __('full_report.branch') }}</label>
            <select name="branch_id" class="form-select">
                <option value="">{{ __('full_report.all_branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary">{{ __('full_report.apply') }}</button></div>
        <div class="col-md-3 d-flex gap-1 flex-wrap">
            <a class="btn btn-sm btn-soft-dark" href="{{ request()->fullUrlWithQuery(['preset' => 'today', 'from' => null, 'to' => null]) }}">{{ __('full_report.today') }}</a>
            <a class="btn btn-sm btn-soft-dark" href="{{ request()->fullUrlWithQuery(['preset' => '7days', 'from' => null, 'to' => null]) }}">{{ __('full_report.last_7') }}</a>
            <a class="btn btn-sm btn-soft-dark" href="{{ request()->fullUrlWithQuery(['preset' => 'month', 'from' => null, 'to' => null]) }}">{{ __('full_report.this_month') }}</a>
            <a class="btn btn-sm btn-soft-dark" href="{{ request()->fullUrlWithQuery(['preset' => 'year', 'from' => null, 'to' => null]) }}">{{ __('full_report.this_year') }}</a>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> {{ __('full_report.print') }}</button>
        </div>
    </div>
    <div class="fr-note mt-2">{{ __('full_report.period') }}: {{ $from->toDateString() }} → {{ $to->toDateString() }} — {{ __('full_report.basis_note') }}</div>
</form>

{{-- Headline KPIs --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.revenue_total') }}</small><strong>{{ $money($revenue['amount']) }}</strong></div></div>
    <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.bookings_total') }}</small><strong>{{ $bookings['total'] }}</strong></div></div>
    <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.new_customers') }}</small><strong>{{ $customers['new'] }}</strong></div></div>
    <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.avg_rating') }}</small><strong>{{ $reviews['total'] ? $reviews['avg_overall'] . ' / 4' : '-' }}</strong></div></div>
</div>

<div class="fr-card">
    <h5><i class="fa-solid fa-chart-line"></i>{{ __('full_report.trend') }}</h5>
    <div id="fr-trend-chart" style="min-height: 280px;"></div>
</div>

<div class="row">
    {{-- Bookings --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-calendar-check"></i>{{ __('full_report.bookings') }}</h5>
            <div class="row g-2 mb-3">
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.total') }}</small><strong>{{ $bookings['total'] }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.paid') }}</small><strong>{{ $bookings['paid'] }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.unpaid') }}</small><strong>{{ $bookings['unpaid'] }}</strong></div></div>
            </div>
            <table class="table fr-table mb-3">
                <thead><tr><th>{{ __('full_report.status') }}</th><th>{{ __('full_report.count') }}</th></tr></thead>
                @forelse ($bookings['by_status'] as $status => $count)
                    <tr><td>{{ $statusLabels[$status] ?? $status }}</td><td>{{ $count }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-muted">-</td></tr>
                @endforelse
            </table>
            <h6>{{ __('full_report.by_branch') }}</h6>
            <table class="table fr-table mb-0">
                <thead><tr><th>{{ __('full_report.branch') }}</th><th>{{ __('full_report.paid_bookings') }}</th><th>{{ __('full_report.services_value') }}</th></tr></thead>
                @forelse ($bookings['by_branch'] as $row)
                    <tr>
                        <td>{{ $row->branch_id ? ($bookings['branch_names'][$row->branch_id]->name ?? '#' . $row->branch_id) : __('full_report.home_service') }}</td>
                        <td>{{ $row->bookings_count }}</td><td>{{ $money($row->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Revenue --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-sack-dollar"></i>{{ __('full_report.revenue') }}</h5>
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.invoices') }}</small><strong>{{ $revenue['invoices'] }}</strong></div></div>
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.amount') }}</small><strong>{{ $money($revenue['amount']) }}</strong></div></div>
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.discounts') }}</small><strong>{{ $money($revenue['discounts']) }}</strong></div></div>
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.tax') }}</small><strong>{{ $money($revenue['tax']) }}</strong></div></div>
            </div>
            <div class="fr-note mb-2">{{ __('full_report.avg_invoice') }}: {{ $money($revenue['average']) }}</div>
            <table class="table fr-table mb-0">
                <thead><tr><th>{{ __('full_report.payment_method') }}</th><th>{{ __('full_report.count') }}</th><th>{{ __('full_report.amount') }}</th></tr></thead>
                @forelse ($revenue['by_method'] as $row)
                    <tr><td>{{ $row->method }}</td><td>{{ $row->total }}</td><td>{{ $money($row->amount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Payments --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-credit-card"></i>{{ __('full_report.payments') }}</h5>
            <div class="row g-2 mb-3">
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.attempts') }}</small><strong>{{ $payments['total'] }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.successful') }}</small><strong>{{ $payments['paid'] }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.success_rate') }}</small><strong>{{ $payments['success_rate'] }}%</strong></div></div>
            </div>
            <table class="table fr-table mb-3">
                @foreach ($payments['by_status'] as $status => $count)
                    <tr><td>{{ $attemptLabels[$status] ?? $status }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </table>
            <table class="table fr-table mb-0">
                <thead><tr><th>{{ __('full_report.gateway') }}</th><th>{{ __('full_report.attempts') }}</th><th>{{ __('full_report.successful') }}</th><th>{{ __('full_report.amount') }}</th></tr></thead>
                @forelse ($payments['by_gateway'] as $row)
                    <tr><td>{{ $row->gateway }}</td><td>{{ $row->total }}</td><td>{{ $row->paid }}</td><td>{{ $money($row->paid_amount) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Customers & staff --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-users"></i>{{ __('full_report.customers_staff') }}</h5>
            <div class="row g-2 mb-3">
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.customers_total') }}</small><strong>{{ $customers['total'] }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.new_customers') }}</small><strong>{{ $customers['new'] }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.employees') }}</small><strong>{{ $customers['employees'] }}</strong></div></div>
            </div>
            <h6>{{ __('full_report.top_staff') }}</h6>
            <table class="table fr-table mb-0">
                <thead><tr><th>{{ __('full_report.staff') }}</th><th>{{ __('full_report.services_count') }}</th><th>{{ __('full_report.services_value') }}</th></tr></thead>
                @forelse ($staff['rows'] as $row)
                    <tr><td>{{ $staff['names'][$row->employee_id]->full_name ?? '#' . $row->employee_id }}</td><td>{{ $row->services_count }}</td><td>{{ $money($row->amount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Services --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-spa"></i>{{ __('full_report.top_services') }}</h5>
            <table class="table fr-table mb-0">
                <thead><tr><th>{{ __('full_report.service') }}</th><th>{{ __('full_report.count') }}</th><th>{{ __('full_report.services_value') }}</th></tr></thead>
                @forelse ($services['rows'] as $row)
                    <tr><td>{{ $services['names'][$row->service_id]->name ?? '#' . $row->service_id }}</td><td>{{ $row->total }}</td><td>{{ $money($row->amount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Coupons --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-ticket"></i>{{ __('full_report.coupons') }}</h5>
            <div class="row g-2 mb-3">
                <div class="col-6"><div class="fr-kpi"><small>{{ __('full_report.redeems') }}</small><strong>{{ $coupons['redeems'] }}</strong></div></div>
                <div class="col-6"><div class="fr-kpi"><small>{{ __('full_report.discounts') }}</small><strong>{{ $money($coupons['discount']) }}</strong></div></div>
            </div>
            <table class="table fr-table mb-0">
                <thead><tr><th>{{ __('full_report.coupon_code') }}</th><th>{{ __('full_report.redeems') }}</th><th>{{ __('full_report.discounts') }}</th></tr></thead>
                @forelse ($coupons['top'] as $row)
                    <tr><td>{{ $row->coupon_code }}</td><td>{{ $row->total }}</td><td>{{ $money($row->discount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Loyalty --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-coins"></i>{{ __('full_report.loyalty') }}</h5>
            <div class="row g-2 mb-3">
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.points_added') }}</small><strong>{{ number_format($loyalty['added']) }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.points_outstanding') }}</small><strong>{{ number_format($loyalty['outstanding']) }}</strong></div></div>
                <div class="col-4"><div class="fr-kpi"><small>{{ __('full_report.points_holders') }}</small><strong>{{ $loyalty['holders'] }}</strong></div></div>
            </div>
            <table class="table fr-table mb-0">
                @forelse ($loyalty['by_action'] as $action => $row)
                    <tr><td>{{ $loyaltyLabels[$action] ?? $action }}</td><td>{{ $row->total }}</td><td>{{ number_format($row->points) }}</td></tr>
                @empty
                    <tr><td class="text-muted">-</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- Reviews --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-star"></i>{{ __('full_report.reviews') }}</h5>
            <div class="row g-2">
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.total') }}</small><strong>{{ $reviews['total'] }}</strong></div></div>
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.avg_overall') }}</small><strong>{{ $reviews['total'] ? $reviews['avg_overall'] : '-' }}</strong></div></div>
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.avg_specialist') }}</small><strong>{{ $reviews['total'] ? $reviews['avg_specialist'] : '-' }}</strong></div></div>
                <div class="col-6 col-md-3"><div class="fr-kpi"><small>{{ __('full_report.avg_service') }}</small><strong>{{ $reviews['total'] ? $reviews['avg_service'] : '-' }}</strong></div></div>
            </div>
            <div class="fr-note mt-2">{{ __('full_report.source_website') }}: {{ $reviews['website'] }} — {{ __('full_report.source_whatsapp') }}: {{ $reviews['whatsapp'] }} ({{ __('full_report.scale_note') }})</div>
        </div>
    </div>

    {{-- Gift cards / orders / payment links --}}
    <div class="col-lg-6">
        <div class="fr-card">
            <h5><i class="fa-solid fa-gift"></i>{{ __('full_report.other_sales') }}</h5>
            <table class="table fr-table mb-0">
                <thead><tr><th></th><th>{{ __('full_report.count') }}</th><th>{{ __('full_report.amount') }}</th></tr></thead>
                <tr><td>{{ __('full_report.gift_cards') }}</td><td>{{ $extras['gift_cards'] }}</td><td>{{ $money($extras['gift_amount']) }}</td></tr>
                <tr><td>{{ __('full_report.product_orders') }}</td><td>{{ $extras['orders'] }}</td><td>{{ $money($extras['orders_amount']) }}</td></tr>
                <tr><td>{{ __('full_report.payment_links_created') }}</td><td>{{ $extras['links_total'] }}</td><td>-</td></tr>
                <tr><td>{{ __('full_report.payment_links_paid') }}</td><td>{{ $extras['links_paid'] }}</td><td>{{ $money($extras['links_paid_amount']) }}</td></tr>
            </table>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"></script>
<script>
    (function () {
        const trend = @json($trend);
        if (!window.ApexCharts || !document.querySelector('#fr-trend-chart')) return;
        new ApexCharts(document.querySelector('#fr-trend-chart'), {
            chart: { type: 'line', height: 280, toolbar: { show: false } },
            stroke: { width: [0, 3], curve: 'smooth' },
            series: [
                { name: "{{ __('full_report.bookings_total') }}", type: 'column', data: trend.map(d => d.bookings) },
                { name: "{{ __('full_report.revenue_total') }}", type: 'line', data: trend.map(d => d.revenue) },
            ],
            xaxis: { categories: trend.map(d => d.day) },
            yaxis: [{ title: { text: "{{ __('full_report.bookings_total') }}" } }, { opposite: true, title: { text: "{{ __('full_report.revenue_total') }}" } }],
            colors: ['#c39b61', '#0f766e'],
        }).render();
    })();
</script>
@endpush
