@extends('backend.layouts.app', ['isBanner' => false])

@section('title') {{ __('dashboard.page_title') }} @endsection

@push('after-styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.css" />
<style>
/* ═══════════════════════════════════════════════════════════════
   JOSPA EXECUTIVE LUXURY DASHBOARD THEME
   ═══════════════════════════════════════════════════════════════ */
:root {
  --exec-bg-card: #ffffff;
  --exec-border: rgba(226, 232, 240, 0.8);
  --exec-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
  --exec-shadow-hover: 0 10px 25px -3px rgba(15, 23, 42, 0.09), 0 4px 10px -2px rgba(15, 23, 42, 0.04);
  --exec-primary: #4f46e5;
  --exec-gold: #d97706;
  --exec-emerald: #059669;
  --exec-rose: #e11d48;
  --exec-sky: #0284c7;
  --exec-violet: #7c3aed;
}

.exec-dashboard {
  padding-bottom: 2.5rem;
}

/* Header bar */
.exec-header {
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
  border-radius: 18px;
  padding: 1.75rem 2rem;
  color: #ffffff;
  margin-bottom: 1.75rem;
  box-shadow: 0 10px 30px -5px rgba(49, 46, 129, 0.25);
  position: relative;
  overflow: hidden;
}
.exec-header::after {
  content: '';
  position: absolute;
  top: -40%;
  right: -10%;
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.exec-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(8px);
  padding: 0.35rem 0.85rem;
  border-radius: 99px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.3px;
  border: 1px solid rgba(255, 255, 255, 0.25);
}

/* KPI Cards */
.kpi-card {
  background: var(--exec-bg-card);
  border: 1px solid var(--exec-border);
  border-radius: 16px;
  padding: 1.25rem 1.25rem;
  position: relative;
  overflow: hidden;
  box-shadow: var(--exec-shadow);
  transition: all 0.25s ease;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--exec-shadow-hover);
}
.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}
.kpi-revenue::before    { background: linear-gradient(90deg, #4f46e5, #818cf8); }
.kpi-net::before        { background: linear-gradient(90deg, #059669, #34d399); }
.kpi-bookings::before   { background: linear-gradient(90deg, #0284c7, #38bdf8); }
.kpi-efficiency::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
.kpi-ticket::before     { background: linear-gradient(90deg, #d97706, #fbbf24); }
.kpi-commission::before { background: linear-gradient(90deg, #e11d48, #f43f5e); }

.kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  margin-bottom: 0.85rem;
}
.kpi-revenue .kpi-icon-wrap    { background: rgba(79, 70, 229, 0.1); color: #4f46e5; }
.kpi-net .kpi-icon-wrap        { background: rgba(5, 150, 105, 0.1); color: #059669; }
.kpi-bookings .kpi-icon-wrap   { background: rgba(2, 132, 199, 0.1); color: #0284c7; }
.kpi-efficiency .kpi-icon-wrap { background: rgba(124, 58, 237, 0.1); color: #7c3aed; }
.kpi-ticket .kpi-icon-wrap     { background: rgba(217, 119, 6, 0.1); color: #d97706; }
.kpi-commission .kpi-icon-wrap { background: rgba(225, 29, 72, 0.1); color: #e11d48; }

.kpi-value {
  font-size: 1.6rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.2;
  margin-bottom: 0.25rem;
  letter-spacing: -0.5px;
}
.kpi-label {
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 600;
  margin-bottom: 0.6rem;
}
.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
}
.kpi-trend.up      { background: #ecfdf5; color: #059669; }
.kpi-trend.down    { background: #fef2f2; color: #dc2626; }
.kpi-trend.neutral { background: #f1f5f9; color: #64748b; }

/* Secondary mini cards */
.mini-stat-card {
  background: var(--exec-bg-card);
  border: 1px solid var(--exec-border);
  border-radius: 14px;
  padding: 1rem 1.15rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: var(--exec-shadow);
  transition: all 0.2s ease;
}
.mini-stat-card:hover { transform: translateY(-2px); }
.mini-stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

/* Chart Cards */
.exec-card {
  background: var(--exec-bg-card);
  border: 1px solid var(--exec-border);
  border-radius: 18px;
  box-shadow: var(--exec-shadow);
  height: 100%;
  display: flex;
  flex-direction: column;
  transition: box-shadow 0.25s ease;
}
.exec-card:hover {
  box-shadow: var(--exec-shadow-hover);
}
.exec-card-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.exec-card-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.exec-card-subtitle {
  font-size: 0.78rem;
  color: #94a3b8;
  margin: 0.15rem 0 0;
}
.exec-card-body {
  padding: 1.5rem;
  flex-grow: 1;
}

/* Quick Report Navigation Buttons */
.report-grid-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  text-decoration: none;
  color: #1e293b;
  transition: all 0.2s ease;
}
.report-grid-card:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  transform: translateX(-3px);
  color: var(--exec-primary);
}
.report-grid-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}

/* Dark mode compatibility */
.dark .kpi-card,
.dark .exec-card,
.dark .mini-stat-card,
.dark .report-grid-card {
  background: #1e293b;
  border-color: #334155;
  color: #f8fafc;
}
.dark .kpi-value,
.dark .exec-card-title {
  color: #f8fafc;
}
.dark .exec-card-header {
  border-color: #334155;
}
</style>
@endpush

@section('content')
@php
    $revenueBreakdown = $data['revenue_breakdown'] ?? [];
    $totalRevBreakdown = array_sum($revenueBreakdown) ?: 1;
    $comparison = $data['comparison'] ?? ['revenue_change' => 0, 'bookings_change' => 0, 'customers_change' => 0];
    $bookingStatus = $data['booking_status'] ?? ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'confirmed' => 0];
    $totalPeriodBookings = $data['total_bookings_period'] ?? 1;
    $fulfillmentRate = $data['fulfillment_rate'] ?? 0;
    $cancellationRate = $data['cancellation_rate'] ?? 0;
@endphp

<div class="exec-dashboard">

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- EXECUTIVE HERO HEADER                                       --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="exec-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="exec-badge-pill"><i class="fa-solid fa-gem text-warning"></i> {{ __('dashboard.executive_suite') }}</span>
          <span class="exec-badge-pill"><i class="fa-solid fa-circle text-success" style="font-size: 8px;"></i> {{ __('dashboard.live_efficiency') }}</span>
        </div>
        <h2 class="mb-1 fw-bolder text-white">{{ __('dashboard.header_title') }}</h2>
        <p class="mb-0 text-white-50 small">
          {{ __('dashboard.header_subtitle') }} &mdash; {{ now()->translatedFormat(__('dashboard.date_format')) }}
        </p>
      </div>

      {{-- Date filter --}}
      <form action="{{ route('backend.home') }}" method="GET" class="d-flex align-items-center gap-2 bg-white bg-opacity-10 p-2 rounded-3 border border-white border-opacity-25 flex-wrap">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white bg-opacity-20 text-white border-0"><i class="fa-solid fa-calendar-days"></i></span>
          <input type="text" name="date_range" value="{{ $date_range }}" class="form-control form-control-sm border-0 dashboard-date-range bg-white text-dark fw-bold" placeholder="{{ __('dashboard.select_period') }}" readonly style="min-width: 210px;">
        </div>
        <button type="submit" name="action" value="filter" class="btn btn-sm btn-light fw-bold px-3">
          <i class="fa-solid fa-filter me-1 text-primary"></i> {{ __('dashboard.update_reports') }}
        </button>
      </form>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 1: 6 PRIMARY EXECUTIVE KPI CARDS                       --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-3">

    {{-- 1. Total Revenue --}}
    <div class="col-12 col-sm-6 col-xl-2">
      <div class="kpi-card kpi-revenue">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="kpi-icon-wrap"><i class="fa-solid fa-vault"></i></div>
            @php $rc = $comparison['revenue_change']; @endphp
            <span class="kpi-trend {{ $rc >= 0 ? 'up' : 'down' }}">
              <i class="fa-solid fa-arrow-{{ $rc >= 0 ? 'trend-up' : 'trend-down' }}"></i>
              {{ abs($rc) }}%
            </span>
          </div>
          <div class="kpi-value">{{ $data['total_revenue'] }}</div>
          <div class="kpi-label">{{ __('dashboard.kpi_total_revenue') }}</div>
        </div>
        <small class="text-muted d-block pt-2 border-top">{{ __('dashboard.kpi_total_revenue_hint') }}</small>
      </div>
    </div>

    {{-- 2. Net Operating Revenue --}}
    <div class="col-12 col-sm-6 col-xl-2">
      <div class="kpi-card kpi-net">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="kpi-icon-wrap"><i class="fa-solid fa-chart-line-up"></i></div>
            <span class="kpi-trend up"><i class="fa-solid fa-shield-halved"></i> {{ __('dashboard.kpi_net') }}</span>
          </div>
          <div class="kpi-value">{{ $data['net_revenue'] }}</div>
          <div class="kpi-label">{{ __('dashboard.kpi_net_revenue') }}</div>
        </div>
        <small class="text-muted d-block pt-2 border-top">{{ __('dashboard.kpi_net_revenue_hint') }}</small>
      </div>
    </div>

    {{-- 3. Completed Bookings --}}
    <div class="col-12 col-sm-6 col-xl-2">
      <div class="kpi-card kpi-bookings">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="kpi-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            @php $bc = $comparison['bookings_change']; @endphp
            <span class="kpi-trend {{ $bc >= 0 ? 'up' : 'down' }}">
              <i class="fa-solid fa-arrow-{{ $bc >= 0 ? 'trend-up' : 'trend-down' }}"></i>
              {{ abs($bc) }}%
            </span>
          </div>
          <div class="kpi-value">{{ number_format($data['total_appointments']) }}</div>
          <div class="kpi-label">{{ __('dashboard.kpi_completed_bookings') }}</div>
        </div>
        <small class="text-muted d-block pt-2 border-top">{{ __('dashboard.kpi_completed_bookings_hint') }}</small>
      </div>
    </div>

    {{-- 4. Fulfillment Rate --}}
    <div class="col-12 col-sm-6 col-xl-2">
      <div class="kpi-card kpi-efficiency">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="kpi-icon-wrap"><i class="fa-solid fa-gauge-high"></i></div>
            <span class="kpi-trend {{ $fulfillmentRate >= 70 ? 'up' : 'neutral' }}">
              {{ $fulfillmentRate >= 70 ? __('dashboard.kpi_excellent_efficiency') : __('dashboard.kpi_normal_efficiency') }}
            </span>
          </div>
          <div class="kpi-value">{{ $fulfillmentRate }}%</div>
          <div class="kpi-label">{{ __('dashboard.kpi_fulfillment_rate') }}</div>
        </div>
        <small class="text-muted d-block pt-2 border-top">{{ __('dashboard.kpi_fulfillment_rate_hint') }}</small>
      </div>
    </div>

    {{-- 5. Average Ticket Size (ARPB) --}}
    <div class="col-12 col-sm-6 col-xl-2">
      <div class="kpi-card kpi-ticket">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="kpi-icon-wrap"><i class="fa-solid fa-basket-shopping"></i></div>
            <span class="kpi-trend neutral"><i class="fa-solid fa-calculator"></i> {{ __('dashboard.kpi_average') }}</span>
          </div>
          <div class="kpi-value">{{ $data['avg_booking_value_formatted'] }}</div>
          <div class="kpi-label">{{ __('dashboard.kpi_avg_ticket') }}</div>
        </div>
        <small class="text-muted d-block pt-2 border-top">{{ __('dashboard.kpi_avg_ticket_hint') }}</small>
      </div>
    </div>

    {{-- 6. Total Staff Commissions --}}
    <div class="col-12 col-sm-6 col-xl-2">
      <div class="kpi-card kpi-commission">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="kpi-icon-wrap"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <span class="kpi-trend down"><i class="fa-solid fa-coins"></i> {{ __('dashboard.kpi_expenses') }}</span>
          </div>
          <div class="kpi-value">{{ $data['total_commission'] }}</div>
          <div class="kpi-label">{{ __('dashboard.kpi_staff_commissions') }}</div>
        </div>
        <small class="text-muted d-block pt-2 border-top">{{ __('dashboard.kpi_staff_commissions_hint') }}</small>
      </div>
    </div>

  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 2: 4 SECONDARY STRATEGIC METRICS                        --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="mini-stat-card">
        <div class="mini-stat-icon bg-info bg-opacity-10 text-info">
          <i class="fa-solid fa-user-plus"></i>
        </div>
        <div>
          <div class="fs-4 fw-bolder mb-0 text-dark">{{ number_format($data['total_new_customers']) }}</div>
          <div class="text-muted small fw-semibold">{{ __('dashboard.new_customers_acquired') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="mini-stat-card">
        <div class="mini-stat-icon bg-warning bg-opacity-10 text-warning">
          <i class="fa-solid fa-gift"></i>
        </div>
        <div>
          <div class="fs-4 fw-bolder mb-0 text-dark">{{ number_format($data['gift_cards_sold']) }} {{ __('dashboard.cards') }}</div>
          <div class="text-muted small fw-semibold">{{ __('dashboard.gift_card_sales') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="mini-stat-card">
        <div class="mini-stat-icon bg-success bg-opacity-10 text-success">
          <i class="fa-solid fa-bag-shopping"></i>
        </div>
        <div>
          <div class="fs-4 fw-bolder mb-0 text-dark">{{ $data['product_sales'] }}</div>
          <div class="text-muted small fw-semibold">{{ __('dashboard.store_product_sales') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="mini-stat-card">
        <div class="mini-stat-icon bg-danger bg-opacity-10 text-danger">
          <i class="fa-solid fa-calendar-xmark"></i>
        </div>
        <div>
          <div class="fs-4 fw-bolder mb-0 text-dark">{{ $cancellationRate }}%</div>
          <div class="text-muted small fw-semibold">{{ __('dashboard.cancellation_rate') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 3: CHARTS SET 1 — REVENUE TREND & VOLUME VS REVENUE     --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    {{-- Chart 1: Daily Revenue Trend --}}
    <div class="col-12 col-xl-8">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-chart-area text-primary"></i> {{ __('dashboard.revenue_trend_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.revenue_trend_subtitle') }}</p>
          </div>
          <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-2">
            <i class="fa-solid fa-arrow-trend-up me-1"></i> {{ __('dashboard.live_flow') }}
          </span>
        </div>
        <div class="exec-card-body">
          <div id="chart-revenue-trend" style="min-height: 320px;"></div>
        </div>
      </div>
    </div>

    {{-- Chart 2: Mixed Volume vs Revenue --}}
    <div class="col-12 col-xl-4">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-layer-group text-warning"></i> {{ __('dashboard.revenue_vs_volume_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.revenue_vs_volume_subtitle') }}</p>
          </div>
        </div>
        <div class="exec-card-body">
          <div id="chart-appointments-revenue" style="min-height: 320px;"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 4: CHARTS SET 2 — OPERATIONS, GAUGE & REVENUE STREAMS   --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    {{-- Chart 3: Booking Status Donut --}}
    <div class="col-12 col-md-6 col-xl-4">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-chart-pie text-success"></i> {{ __('dashboard.booking_status_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.booking_status_subtitle') }}</p>
          </div>
        </div>
        <div class="exec-card-body text-center">
          <div id="chart-booking-status" style="min-height: 260px;"></div>
          <div class="d-flex justify-content-around mt-3 pt-3 border-top small text-muted">
            <div><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#10b981;"></span>{{ __('dashboard.status_completed') }}: <strong>{{ $bookingStatus['completed'] }}</strong></div>
            <div><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#3b82f6;"></span>{{ __('dashboard.status_confirmed') }}: <strong>{{ $bookingStatus['confirmed'] }}</strong></div>
            <div><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#f59e0b;"></span>{{ __('dashboard.status_pending') }}: <strong>{{ $bookingStatus['pending'] }}</strong></div>
            <div><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#ef4444;"></span>{{ __('dashboard.status_cancelled') }}: <strong>{{ $bookingStatus['cancelled'] }}</strong></div>
          </div>
        </div>
      </div>
    </div>

    {{-- Chart 4: Efficiency & Cancellation Gauge --}}
    <div class="col-12 col-md-6 col-xl-4">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-compass-drafting text-danger"></i> {{ __('dashboard.efficiency_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.efficiency_subtitle') }}</p>
          </div>
        </div>
        <div class="exec-card-body text-center">
          <div id="chart-efficiency-gauge" style="min-height: 260px;"></div>
          <div class="p-2 rounded bg-light mt-2 small text-muted">
            {{ __('dashboard.total_period_bookings') }}: <strong class="text-dark">{{ $totalPeriodBookings }} {{ __('dashboard.booking_unit') }}</strong>
          </div>
        </div>
      </div>
    </div>

    {{-- Chart 5: Revenue Streams Breakdown --}}
    <div class="col-12 col-xl-4">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-chart-column text-indigo"></i> {{ __('dashboard.revenue_streams_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.revenue_streams_subtitle') }}</p>
          </div>
        </div>
        <div class="exec-card-body">
          <div id="chart-revenue-breakdown" style="min-height: 260px;"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 5: CHARTS SET 3 — PEAK HOURS & BRANCH PERFORMANCE       --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    {{-- Chart 6: Peak Booking Hours --}}
    <div class="col-12 col-xl-6">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-clock text-info"></i> {{ __('dashboard.peak_hours_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.peak_hours_subtitle') }}</p>
          </div>
          <span class="badge bg-info bg-opacity-10 text-info fw-bold">{{ __('dashboard.scheduling_efficiency') }}</span>
        </div>
        <div class="exec-card-body">
          <div id="chart-peak-hours" style="min-height: 280px;"></div>
        </div>
      </div>
    </div>

    {{-- Chart 7: Branch Performance Comparison --}}
    <div class="col-12 col-xl-6">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-shop text-primary"></i> {{ __('dashboard.branch_performance_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.branch_performance_subtitle') }}</p>
          </div>
          <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">{{ __('dashboard.branch_coverage') }}</span>
        </div>
        <div class="exec-card-body">
          <div id="chart-branch-performance" style="min-height: 280px;"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 6: CHARTS SET 4 — PAYMENT METHODS & STAFF PRODUCTIVITY  --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    {{-- Chart 8: Payment Methods --}}
    <div class="col-12 col-xl-4">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-credit-card text-success"></i> {{ __('dashboard.payment_methods_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.payment_methods_subtitle') }}</p>
          </div>
        </div>
        <div class="exec-card-body">
          <div id="chart-payment-methods" style="min-height: 270px;"></div>
        </div>
      </div>
    </div>

    {{-- Chart 9: Staff Productivity Revenue --}}
    <div class="col-12 col-xl-8">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-award text-warning"></i> {{ __('dashboard.staff_productivity_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.staff_productivity_subtitle') }}</p>
          </div>
          <a href="{{ route('backend.reports.staff-report') }}" class="btn btn-sm btn-outline-primary">
            {{ __('dashboard.detailed_staff_report') }} <i class="fa-solid fa-arrow-left ms-1"></i>
          </a>
        </div>
        <div class="exec-card-body">
          <div id="chart-staff-productivity" style="min-height: 270px;"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 7: TOP PERFORMING SERVICES & STAFF LEADERBOARD          --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    {{-- Top Services --}}
    <div class="col-12 col-xl-6">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-spa text-info"></i> {{ __('dashboard.top_services_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.top_services_subtitle') }}</p>
          </div>
          <a href="{{ route('backend.reports.sales-by-date') }}" class="btn btn-sm btn-link text-decoration-none">{{ __('dashboard.view_all') }} &larr;</a>
        </div>
        <div class="exec-card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr class="small text-muted">
                  <th>{{ __('dashboard.col_service') }}</th>
                  <th class="text-center">{{ __('dashboard.col_sessions') }}</th>
                  <th class="text-end">{{ __('dashboard.col_total_revenue') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($data['top_services'] as $svc)
                  @php
                    $sName = optional($svc->service)->name;
                    $displayName = is_array($sName) ? ($sName[app()->getLocale()] ?? reset($sName)) : (json_decode($sName, true)[app()->getLocale()] ?? $sName);
                  @endphp
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary bg-opacity-10 text-primary rounded p-2" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                          <i class="fa-solid fa-spa"></i>
                        </div>
                        <div>
                          <div class="fw-bold text-dark">{{ $displayName ?: __('dashboard.default_service_name') }}</div>
                          <small class="text-muted">{{ __('dashboard.code') }}: #{{ $svc->service_id }}</small>
                        </div>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-light text-dark fw-bold px-2 py-1">{{ number_format($svc->total_service_count) }}</span>
                    </td>
                    <td class="text-end fw-bolder text-success">
                      {{ \Currency::format($svc->total_service_price) }}
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center py-4 text-muted">{{ __('dashboard.no_services') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Staff Productivity Leaderboard Table --}}
    <div class="col-12 col-xl-6">
      <div class="exec-card">
        <div class="exec-card-header">
          <div>
            <h5 class="exec-card-title"><i class="fa-solid fa-trophy text-warning"></i> {{ __('dashboard.staff_leaderboard_title') }}</h5>
            <p class="exec-card-subtitle">{{ __('dashboard.staff_leaderboard_subtitle') }}</p>
          </div>
          <a href="{{ route('backend.reports.payout-report') }}" class="btn btn-sm btn-link text-decoration-none">{{ __('dashboard.commissions_report') }} &larr;</a>
        </div>
        <div class="exec-card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr class="small text-muted">
                  <th>{{ __('dashboard.col_staff') }}</th>
                  <th class="text-center">{{ __('dashboard.col_bookings') }}</th>
                  <th class="text-end">{{ __('dashboard.col_earned_revenue') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($data['top_staff'] as $idx => $staff)
                  @php
                    $emp = $staff->employee;
                    $staffName = $emp ? trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')) : __('dashboard.specialist') . ' #' . $staff->employee_id;
                    $staffName = $staffName ?: ($emp->username ?? __('dashboard.default_staff_name'));
                  @endphp
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning bg-opacity-20 text-warning rounded-pill" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:11px;">
                          {{ $idx + 1 }}
                        </span>
                        <div>
                          <div class="fw-bold text-dark">{{ $staffName }}</div>
                          <small class="text-muted">{{ $emp->email ?? __('dashboard.certified') }}</small>
                        </div>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-info bg-opacity-10 text-info fw-bold">{{ $staff->total_bookings }} {{ __('dashboard.booking_unit') }}</span>
                    </td>
                    <td class="text-end fw-bolder text-primary">
                      {{ \Currency::format($staff->total_revenue) }}
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center py-4 text-muted">{{ __('dashboard.no_staff') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 8: EXECUTIVE REPORT COMMAND CENTER                      --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="exec-card mb-4">
    <div class="exec-card-header">
      <div>
        <h5 class="exec-card-title"><i class="fa-solid fa-folder-tree text-primary"></i> {{ __('dashboard.reports_hub_title') }}</h5>
        <p class="exec-card-subtitle">{{ __('dashboard.reports_hub_subtitle') }}</p>
      </div>
    </div>
    <div class="exec-card-body">
      <div class="row g-2">
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.financial-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_financial') }}</div><small class="text-muted">{{ __('dashboard.rpt_financial_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.sales-by-date') }}" class="report-grid-card">
            <div class="report-grid-icon bg-success bg-opacity-10 text-success"><i class="fa-solid fa-chart-line"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_sales_by_date') }}</div><small class="text-muted">{{ __('dashboard.rpt_sales_by_date_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.daily-booking-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-info bg-opacity-10 text-info"><i class="fa-solid fa-calendar-day"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_daily_bookings') }}</div><small class="text-muted">{{ __('dashboard.rpt_daily_bookings_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.overall-booking-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-calendar-days"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_overall_bookings') }}</div><small class="text-muted">{{ __('dashboard.rpt_overall_bookings_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.payout-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_commissions') }}</div><small class="text-muted">{{ __('dashboard.rpt_commissions_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.staff-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-purple bg-opacity-10 text-purple" style="color:#7c3aed;"><i class="fa-solid fa-users"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_staff') }}</div><small class="text-muted">{{ __('dashboard.rpt_staff_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.order-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-secondary bg-opacity-10 text-secondary"><i class="fa-solid fa-box-open"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_products') }}</div><small class="text-muted">{{ __('dashboard.rpt_products_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.coupon-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-teal bg-opacity-10" style="color:#0d9488;"><i class="fa-solid fa-ticket"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_coupons') }}</div><small class="text-muted">{{ __('dashboard.rpt_coupons_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.promotion-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-pink bg-opacity-10" style="color:#db2777;"><i class="fa-solid fa-bullhorn"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_promotions') }}</div><small class="text-muted">{{ __('dashboard.rpt_promotions_hint') }}</small></div>
          </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <a href="{{ route('backend.reports.payment-transactions-report') }}" class="report-grid-card">
            <div class="report-grid-icon bg-dark bg-opacity-10 text-dark"><i class="fa-solid fa-money-bill-transfer"></i></div>
            <div><div class="fw-bold small">{{ __('dashboard.rpt_payments') }}</div><small class="text-muted">{{ __('dashboard.rpt_payments_hint') }}</small></div>
          </a>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('after-scripts')
{{-- Clean ApexCharts CDN load with NO integrity attribute so edge/browser never blocks execution --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"></script>

<script>
(function() {
  function renderAllExecutiveCharts() {
    if (typeof ApexCharts === 'undefined') {
      console.warn('[Jospa Executive] ApexCharts is still loading, waiting...');
      setTimeout(renderAllExecutiveCharts, 250);
      return;
    }

    /* ── Date range picker ── */
    if (typeof flatpickr !== 'undefined') {
      document.querySelectorAll('.dashboard-date-range').forEach(function(el) {
        flatpickr(el, { mode: "range" });
      });
    }

    const isRtl = document.documentElement.getAttribute('dir') === 'rtl';

    /* ═══════════════════════════════════════════════════════════
       CHART 1: MAIN DAILY REVENUE TREND (AREA)
       ═══════════════════════════════════════════════════════════ */
    try {
      const trendEl = document.querySelector('#chart-revenue-trend');
      if (trendEl) {
        const trendData  = @json($data['revenue_chart']['total_price'] ?? []);
        const trendDates = @json($data['revenue_chart']['xaxis'] ?? []);

        new ApexCharts(trendEl, {
          series: [{ name: @json(__('dashboard.chart_earned_revenue')) + ' (' + @json(__('dashboard.currency_short')) + ')', data: trendData }],
          chart: {
            type: 'area',
            height: 330,
            toolbar: { show: true, tools: { download: true, zoom: false, pan: false } },
            fontFamily: 'inherit'
          },
          colors: ['#4f46e5'],
          fill: {
            type: 'gradient',
            gradient: {
              shadeIntensity: 1,
              opacityFrom: 0.45,
              opacityTo: 0.05,
              stops: [0, 95, 100]
            }
          },
          stroke: { curve: 'smooth', width: 3 },
          dataLabels: { enabled: false },
          xaxis: {
            categories: trendDates,
            labels: { style: { fontSize: '11px', colors: '#64748b' } },
            axisBorder: { show: false }
          },
          yaxis: {
            labels: {
              style: { fontSize: '11px', colors: '#64748b' },
              formatter: v => Number(v || 0).toLocaleString() + ' ' + @json(__('dashboard.currency_short'))
            }
          },
          grid: { strokeDashArray: 4, borderColor: '#f1f5f9' },
          tooltip: {
            y: { formatter: v => Number(v || 0).toLocaleString() + ' ' + @json(__('dashboard.currency_long')) }
          }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 1:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 2: APPOINTMENTS VS REVENUE (MIXED)
       ═══════════════════════════════════════════════════════════ */
    try {
      const mixedEl = document.querySelector('#chart-appointments-revenue');
      if (mixedEl) {
        const bookingsData = @json($data['revenue_chart']['total_bookings'] ?? []);
        const revenueData  = @json($data['revenue_chart']['total_price'] ?? []);
        const chartDates   = @json($data['revenue_chart']['xaxis'] ?? []);

        new ApexCharts(mixedEl, {
          series: [
            { name: @json(__('dashboard.chart_revenue')), type: 'area', data: revenueData },
            { name: @json(__('dashboard.chart_bookings')), type: 'column', data: bookingsData }
          ],
          colors: ['#4f46e5', '#f59e0b'],
          chart: { type: 'line', height: 330, toolbar: { show: false }, fontFamily: 'inherit' },
          stroke: { width: [3, 0], curve: 'smooth' },
          fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: .35, opacityTo: .05 } },
          dataLabels: { enabled: false },
          xaxis: { categories: chartDates, labels: { style: { fontSize: '10px' } } },
          yaxis: [
            { title: { text: @json(__('dashboard.chart_revenue')) + ' (' + @json(__('dashboard.currency_short')) + ')' }, labels: { formatter: v => Number(v||0).toLocaleString() } },
            { opposite: true, title: { text: @json(__('dashboard.chart_bookings_count')) }, labels: { formatter: v => Number(v||0).toFixed(0) }, min: 0 }
          ],
          legend: { position: 'top', fontSize: '12px' },
          grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 2:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 3: BOOKING STATUS DONUT
       ═══════════════════════════════════════════════════════════ */
    try {
      const statusEl = document.querySelector('#chart-booking-status');
      if (statusEl) {
        const bs = [
          {{ $bookingStatus['completed'] }},
          {{ $bookingStatus['confirmed'] }},
          {{ $bookingStatus['pending'] }},
          {{ $bookingStatus['cancelled'] }}
        ];

        new ApexCharts(statusEl, {
          series: bs,
          labels: [@json(__('dashboard.chart_completed')), @json(__('dashboard.chart_confirmed')), @json(__('dashboard.chart_pending')), @json(__('dashboard.chart_cancelled'))],
          colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
          chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
          legend: { show: false },
          plotOptions: {
            pie: {
              donut: {
                size: '72%',
                labels: {
                  show: true,
                  total: {
                    show: true,
                    label: @json(__('dashboard.chart_total_bookings')),
                    fontSize: '12px',
                    formatter: () => '{{ $totalPeriodBookings }}'
                  }
                }
              }
            }
          },
          dataLabels: { enabled: false }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 3:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 4: EFFICIENCY & CANCELLATION GAUGE (RADIAL)
       ═══════════════════════════════════════════════════════════ */
    try {
      const gaugeEl = document.querySelector('#chart-efficiency-gauge');
      if (gaugeEl) {
        new ApexCharts(gaugeEl, {
          series: [{{ $fulfillmentRate }}, {{ $cancellationRate }}],
          chart: { type: 'radialBar', height: 260, fontFamily: 'inherit' },
          plotOptions: {
            radialBar: {
              hollow: { size: '55%' },
              dataLabels: {
                name: { fontSize: '13px', offsetY: -6 },
                value: { fontSize: '1.4rem', fontWeight: 800, formatter: v => v + '%' },
                total: {
                  show: true,
                  label: @json(__('dashboard.chart_fulfillment')),
                  formatter: () => '{{ $fulfillmentRate }}%'
                }
              }
            }
          },
          colors: ['#10b981', '#ef4444'],
          labels: [@json(__('dashboard.chart_fulfillment_rate')), @json(__('dashboard.chart_cancellation_rate'))]
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 4:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 5: REVENUE STREAMS (HORIZONTAL BAR)
       ═══════════════════════════════════════════════════════════ */
    try {
      const streamEl = document.querySelector('#chart-revenue-breakdown');
      if (streamEl) {
        new ApexCharts(streamEl, {
          series: [{
            name: @json(__('dashboard.chart_income')) + ' (' + @json(__('dashboard.currency_short')) + ')',
            data: [
              {{ $revenueBreakdown['services'] ?? 0 }},
              {{ $revenueBreakdown['packages'] ?? 0 }},
              {{ $revenueBreakdown['giftcards'] ?? 0 }},
              {{ $revenueBreakdown['products'] ?? 0 }}
            ]
          }],
          chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
          colors: ['#4f46e5', '#d97706', '#ec4899', '#059669'],
          plotOptions: {
            bar: {
              horizontal: true,
              distributed: true,
              borderRadius: 6,
              barHeight: '55%'
            }
          },
          dataLabels: {
            enabled: true,
            formatter: v => Number(v || 0).toLocaleString() + ' ' + @json(__('dashboard.currency_short'))
          },
          xaxis: {
            categories: [@json(__('dashboard.stream_services')), @json(__('dashboard.stream_packages')), @json(__('dashboard.stream_giftcards')), @json(__('dashboard.stream_products'))],
            labels: { style: { fontSize: '11px' } }
          },
          yaxis: { labels: { style: { fontSize: '12px', fontWeight: 600 } } },
          legend: { show: false },
          grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 5:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 6: PEAK BOOKING HOURS (COLUMN)
       ═══════════════════════════════════════════════════════════ */
    try {
      const peakEl = document.querySelector('#chart-peak-hours');
      if (peakEl) {
        new ApexCharts(peakEl, {
          series: [{
            name: @json(__('dashboard.chart_sessions')),
            data: @json($data['peak_hours_data'] ?? [])
          }],
          chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
          colors: ['#0284c7'],
          plotOptions: {
            bar: {
              borderRadius: 6,
              columnWidth: '50%',
              dataLabels: { position: 'top' }
            }
          },
          dataLabels: {
            enabled: true,
            offsetY: -18,
            style: { fontSize: '10px', colors: ['#0284c7'] }
          },
          xaxis: {
            categories: @json($data['peak_hours_labels'] ?? []),
            labels: { style: { fontSize: '10px' } }
          },
          yaxis: { labels: { formatter: v => Number(v || 0).toFixed(0) } },
          grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 6:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 7: BRANCH PERFORMANCE (GROUPED BAR)
       ═══════════════════════════════════════════════════════════ */
    try {
      const branchEl = document.querySelector('#chart-branch-performance');
      if (branchEl) {
        new ApexCharts(branchEl, {
          series: [
            { name: @json(__('dashboard.chart_total_revenue')) + ' (' + @json(__('dashboard.currency_short')) + ')', data: @json($data['branch_chart_revenue'] ?? []) },
            { name: @json(__('dashboard.chart_completed_bookings')), data: @json($data['branch_chart_bookings'] ?? []) }
          ],
          colors: ['#4f46e5', '#10b981'],
          chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
          plotOptions: { bar: { horizontal: false, columnWidth: '45%', borderRadius: 5 } },
          dataLabels: { enabled: false },
          xaxis: { categories: @json($data['branch_chart_names'] ?? []) },
          yaxis: [
            { title: { text: @json(__('dashboard.chart_revenue')) + ' (' + @json(__('dashboard.currency_short')) + ')' }, labels: { formatter: v => Number(v||0).toLocaleString() } },
            { opposite: true, title: { text: @json(__('dashboard.chart_bookings')) }, labels: { formatter: v => Number(v||0).toFixed(0) } }
          ],
          legend: { position: 'top', fontSize: '12px' },
          grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 7:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 8: PAYMENT METHODS (DONUT)
       ═══════════════════════════════════════════════════════════ */
    try {
      const paymentEl = document.querySelector('#chart-payment-methods');
      if (paymentEl) {
        new ApexCharts(paymentEl, {
          series: @json($data['payment_chart_series'] ?? [1]),
          labels: @json($data['payment_chart_labels'] ?? [__('dashboard.chart_payments')]),
          chart: { type: 'donut', height: 270, fontFamily: 'inherit' },
          colors: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'],
          legend: { position: 'bottom', fontSize: '11px' },
          dataLabels: { enabled: true, formatter: v => v.toFixed(0) + '%' }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 8:', e); }

    /* ═══════════════════════════════════════════════════════════
       CHART 9: TOP STAFF PRODUCTIVITY (HORIZONTAL BAR)
       ═══════════════════════════════════════════════════════════ */
    try {
      const staffEl = document.querySelector('#chart-staff-productivity');
      if (staffEl) {
        new ApexCharts(staffEl, {
          series: [{
            name: @json(__('dashboard.chart_services_value')) + ' (' + @json(__('dashboard.currency_short')) + ')',
            data: @json($data['staff_chart_revenue'] ?? [])
          }],
          chart: { type: 'bar', height: 270, toolbar: { show: false }, fontFamily: 'inherit' },
          colors: ['#d97706'],
          plotOptions: {
            bar: {
              horizontal: true,
              borderRadius: 6,
              barHeight: '50%'
            }
          },
          dataLabels: {
            enabled: true,
            formatter: v => Number(v || 0).toLocaleString() + ' ' + @json(__('dashboard.currency_short'))
          },
          xaxis: { categories: @json($data['staff_chart_names'] ?? []) },
          grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        }).render();
      }
    } catch(e) { console.error('Error rendering Chart 9:', e); }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderAllExecutiveCharts);
  } else {
    renderAllExecutiveCharts();
  }
})();
</script>
@endpush
