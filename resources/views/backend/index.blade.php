@extends('backend.layouts.app', ['isBanner' => false])

@section('title') {{ 'Executive Dashboard' }} @endsection

@section('content')
@php
    $revenueBreakdown = $data['revenue_breakdown'] ?? [];
    $totalRevBreakdown = array_sum($revenueBreakdown) ?: 1;
    $comparison = $data['comparison'] ?? ['revenue_change' => 0, 'bookings_change' => 0, 'customers_change' => 0];
    $bookingStatus = $data['booking_status'] ?? ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'confirmed' => 0];
@endphp

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- PAGE HEADER                                                 --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="exec-dashboard">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h2 class="mb-0 fw-bold exec-title">{{ __('dashboard.lbl_performance') }}</h2>
      <p class="text-muted mb-0 small">{{ now()->format('l, d F Y') }} &mdash; Executive Overview</p>
    </div>
    <form action="{{ route('backend.home') }}" class="d-flex align-items-center gap-2 flex-wrap">
      <div class="form-group my-0">
        <input type="text" name="date_range" value="{{ $date_range }}" class="form-control dashboard-date-range" placeholder="Select date range" readonly>
      </div>
      <button type="submit" name="action" value="filter" class="btn btn-primary px-4">
        <i class="fa-solid fa-filter me-1"></i> {{ __('dashboard.lbl_submit') }}
      </button>
    </form>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 1: KPI CARDS (6 cards)                                 --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">

    {{-- Completed Appointments --}}
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kpi-card kpi-appointments">
        <div class="kpi-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="kpi-body">
          <div class="kpi-value">{{ number_format($data['total_appointments']) }}</div>
          <div class="kpi-label">{{ __('dashboard.lbl_appointment') }}</div>
          @php $bc = $comparison['bookings_change']; @endphp
          <div class="kpi-trend {{ $bc >= 0 ? 'up' : 'down' }}">
            <i class="fa-solid fa-arrow-{{ $bc >= 0 ? 'trend-up' : 'trend-down' }}"></i>
            {{ abs($bc) }}% vs prev
          </div>
        </div>
        <a href="{{ route('backend.bookings.datatable_view') }}" class="kpi-link"></a>
      </div>
    </div>

    {{-- Total Revenue --}}
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kpi-card kpi-revenue">
        <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
        <div class="kpi-body">
          <div class="kpi-value">{{ $data['total_revenue'] }}</div>
          <div class="kpi-label">{{ __('dashboard.lbl_tot_revenue') }}</div>
          @php $rc = $comparison['revenue_change']; @endphp
          <div class="kpi-trend {{ $rc >= 0 ? 'up' : 'down' }}">
            <i class="fa-solid fa-arrow-{{ $rc >= 0 ? 'trend-up' : 'trend-down' }}"></i>
            {{ abs($rc) }}% vs prev
          </div>
        </div>
        <a href="{{ route('backend.reports.financial-report') }}" class="kpi-link"></a>
      </div>
    </div>

    {{-- Commission --}}
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kpi-card kpi-commission">
        <div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <div class="kpi-body">
          <div class="kpi-value">{{ $data['total_commission'] }}</div>
          <div class="kpi-label">{{ __('dashboard.lbl_sales_commission') }}</div>
          <div class="kpi-trend neutral"><i class="fa-solid fa-minus"></i> Commission paid</div>
        </div>
        <a href="{{ route('backend.reports.payout-report') }}" class="kpi-link"></a>
      </div>
    </div>

    {{-- New Customers --}}
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kpi-card kpi-customers">
        <div class="kpi-icon"><i class="fa-solid fa-user-plus"></i></div>
        <div class="kpi-body">
          <div class="kpi-value">{{ number_format($data['total_new_customers']) }}</div>
          <div class="kpi-label">{{ __('dashboard.lbl_new_customer') }}</div>
          @php $cc = $comparison['customers_change']; @endphp
          <div class="kpi-trend {{ $cc >= 0 ? 'up' : 'down' }}">
            <i class="fa-solid fa-arrow-{{ $cc >= 0 ? 'trend-up' : 'trend-down' }}"></i>
            {{ abs($cc) }}% vs prev
          </div>
        </div>
        <a href="{{ route('backend.customers.index') }}" class="kpi-link"></a>
      </div>
    </div>

    {{-- Product Orders --}}
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kpi-card kpi-orders">
        <div class="kpi-icon"><i class="fa-solid fa-box-open"></i></div>
        <div class="kpi-body">
          <div class="kpi-value">{{ number_format($data['total_orders']) }}</div>
          <div class="kpi-label">{{ __('dashboard.lbl_orders') }}</div>
          <div class="kpi-sub">{{ $data['product_sales'] }} earnings</div>
        </div>
        <a href="{{ route('backend.orders.index') }}" class="kpi-link"></a>
      </div>
    </div>

    {{-- Gift Cards --}}
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kpi-card kpi-giftcards">
        <div class="kpi-icon"><i class="fa-solid fa-gift"></i></div>
        <div class="kpi-body">
          <div class="kpi-value">{{ number_format($data['gift_cards_sold'] ?? 0) }}</div>
          <div class="kpi-label">Gift Cards Sold</div>
          <div class="kpi-sub">{{ \Currency::format($revenueBreakdown['giftcards'] ?? 0) }}</div>
        </div>
      </div>
    </div>

  </div>{{-- /ROW 1 --}}

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 2: MAIN REVENUE CHART + BOOKING STATUS + UPCOMING      --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">

    {{-- Revenue Trend Chart --}}
    <div class="col-xl-8">
      <div class="card exec-card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-0"><i class="fa-solid fa-chart-line text-primary me-2"></i>Revenue &amp; Bookings Trend</h5>
            <p class="text-muted mb-0 small">Completed bookings revenue over selected period</p>
          </div>
          <a href="{{ route('backend.reports.sales-by-date') }}" class="btn btn-sm btn-outline-primary">View Full Report</a>
        </div>
        <div class="card-body">
          <div id="chart-main-trend" style="min-height:300px;"></div>
        </div>
      </div>
    </div>

    {{-- Booking Status Donut --}}
    <div class="col-xl-4">
      <div class="card exec-card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fa-solid fa-circle-half-stroke text-warning me-2"></i>Booking Status</h5>
          <p class="text-muted mb-0 small">{{ number_format($data['total_bookings_period'] ?? 0) }} total bookings</p>
        </div>
        <div class="card-body d-flex flex-column align-items-center justify-content-center">
          <div id="chart-booking-status" style="min-height:240px;width:100%;"></div>
          <div class="booking-status-legend mt-2 w-100">
            <div class="d-flex justify-content-between flex-wrap gap-1">
              <span class="badge-status completed"><i class="fa-solid fa-circle me-1"></i>Completed: {{ $bookingStatus['completed'] }}</span>
              <span class="badge-status confirmed"><i class="fa-solid fa-circle me-1"></i>Confirmed: {{ $bookingStatus['confirmed'] }}</span>
              <span class="badge-status pending"><i class="fa-solid fa-circle me-1"></i>Pending: {{ $bookingStatus['pending'] }}</span>
              <span class="badge-status cancelled"><i class="fa-solid fa-circle me-1"></i>Cancelled: {{ $bookingStatus['cancelled'] }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>{{-- /ROW 2 --}}

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 3: REVENUE BREAKDOWN + CANCELLATION + UPCOMING         --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">

    {{-- Revenue by Source (Bar chart) --}}
    <div class="col-xl-5">
      <div class="card exec-card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-0"><i class="fa-solid fa-chart-bar text-success me-2"></i>Revenue by Source</h5>
            <p class="text-muted mb-0 small">Breakdown of income streams</p>
          </div>
        </div>
        <div class="card-body">
          <div id="chart-revenue-breakdown" style="min-height:280px;"></div>
        </div>
        <div class="card-footer bg-transparent border-0">
          <div class="row text-center g-2">
            <div class="col-3">
              <div class="src-badge src-services">
                <div class="src-val">{{ \Currency::format($revenueBreakdown['services'] ?? 0) }}</div>
                <div class="src-lbl">Services</div>
              </div>
            </div>
            <div class="col-3">
              <div class="src-badge src-packages">
                <div class="src-val">{{ \Currency::format($revenueBreakdown['packages'] ?? 0) }}</div>
                <div class="src-lbl">Packages</div>
              </div>
            </div>
            <div class="col-3">
              <div class="src-badge src-products">
                <div class="src-val">{{ \Currency::format($revenueBreakdown['products'] ?? 0) }}</div>
                <div class="src-lbl">Products</div>
              </div>
            </div>
            <div class="col-3">
              <div class="src-badge src-gifts">
                <div class="src-val">{{ \Currency::format($revenueBreakdown['giftcards'] ?? 0) }}</div>
                <div class="src-lbl">Gift Cards</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Cancellation Rate + Quick Stats --}}
    <div class="col-xl-3">
      <div class="card exec-card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fa-solid fa-gauge-high text-danger me-2"></i>Operational KPIs</h5>
        </div>
        <div class="card-body">
          {{-- Cancellation rate radial --}}
          <div class="text-center mb-3">
            <div id="chart-cancellation-rate" style="min-height:200px;"></div>
            <p class="text-muted small mt-1">Cancellation Rate</p>
          </div>
          <hr>
          <div class="kpi-mini-row">
            <div class="kpi-mini">
              <i class="fa-solid fa-ban text-danger"></i>
              <div>
                <div class="fw-bold">{{ $bookingStatus['cancelled'] }}</div>
                <div class="text-muted small">Cancelled</div>
              </div>
            </div>
            <div class="kpi-mini">
              <i class="fa-solid fa-hourglass-half text-warning"></i>
              <div>
                <div class="fw-bold">{{ $bookingStatus['pending'] }}</div>
                <div class="text-muted small">Pending</div>
              </div>
            </div>
          </div>
          <div class="kpi-mini-row mt-2">
            <div class="kpi-mini">
              <i class="fa-solid fa-circle-check text-success"></i>
              <div>
                <div class="fw-bold">{{ $bookingStatus['completed'] }}</div>
                <div class="text-muted small">Completed</div>
              </div>
            </div>
            <div class="kpi-mini">
              <i class="fa-solid fa-calendar-check text-primary"></i>
              <div>
                <div class="fw-bold">{{ $bookingStatus['confirmed'] }}</div>
                <div class="text-muted small">Confirmed</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Upcoming Appointments --}}
    <div class="col-xl-4">
      <div class="card exec-card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0"><i class="fa-solid fa-clock text-info me-2"></i>{{ __('dashboard.lbl_upcoming_appointment') }}</h5>
          <a href="{{ route('backend.bookings.index') }}" class="btn btn-sm btn-outline-info">{{ __('messages.view_all') }}</a>
        </div>
        <div class="card-body p-0">
          <div class="upcoming-list">
            @forelse ($data['upcomming_appointments'] as $booking)
            <div class="upcoming-item">
              <img src="{{ $booking->user->profile_image ?? default_user_avatar() }}" class="rounded-circle avatar-sm" alt="">
              <div class="upcoming-info flex-grow-1">
                <div class="fw-semibold">{{ $booking->user->full_name ?? default_user_name() }}</div>
                <div class="text-muted small">{{ date('d M, g:i A', strtotime($booking->start_date_time)) }}</div>
              </div>
              <div class="text-end">
                <span class="badge bg-info-subtle text-info">
                  @php
                      $timezone = setting('default_time_zone') ?? 'UTC';
                      $currentDateTime = Carbon\Carbon::now($timezone);
                      $dateTime = Carbon\Carbon::parse($booking->start_date_time, $timezone);
                      $timeUntil = $currentDateTime->copy()->add($dateTime->diff())->diffForHumans(null, true);
                  @endphp
                  in {{ $timeUntil }}
                </span>
                <a href="{{ route('backend.bookings.index', ['booking_id' => $booking->id]) }}" class="d-block text-primary mt-1 small">
                  <i class="fa-solid fa-arrow-right"></i>
                </a>
              </div>
            </div>
            @empty
            <div class="p-4 text-center text-muted">
              <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block opacity-50"></i>
              {{ __('dashboard.lbl_upcoming_bookings') }}
            </div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

  </div>{{-- /ROW 3 --}}

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 4: APPOINTMENTS vs REVENUE + TOP SERVICES + TOP STAFF  --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">

    {{-- Mixed chart: appointments vs revenue --}}
    <div class="col-xl-5">
      <div class="card exec-card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-0"><i class="fa-solid fa-chart-mixed text-purple me-2"></i>Appointments vs Revenue</h5>
            <p class="text-muted mb-0 small">Dual-axis comparison</p>
          </div>
        </div>
        <div class="card-body">
          <div id="chart-appt-vs-revenue" style="min-height:280px;"></div>
        </div>
      </div>
    </div>

    {{-- Top Services --}}
    <div class="col-xl-3">
      <div class="card exec-card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fa-solid fa-star text-warning me-2"></i>{{ __('dashboard.lbl_top_services') }}</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-sm exec-table mb-0">
              <thead>
                <tr>
                  <th>Service</th>
                  <th class="text-center">Count</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['top_services'] as $service)
                <tr>
                  <td class="fw-medium">{{ Str::limit($service->service->name ?? '—', 20) }}</td>
                  <td class="text-center"><span class="badge bg-primary-subtle text-primary">{{ $service->total_service_count }}</span></td>
                  <td class="text-end text-success fw-semibold">{{ Currency::format($service->total_service_price) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted py-3">{{ __('messages.top_service_notfound') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-transparent">
          <a href="{{ route('backend.reports.sales-by-date') }}" class="btn btn-sm btn-outline-primary w-100">Full Sales Report</a>
        </div>
      </div>
    </div>

    {{-- Top Staff --}}
    <div class="col-xl-4">
      <div class="card exec-card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fa-solid fa-users text-info me-2"></i>Top Performing Staff</h5>
        </div>
        <div class="card-body p-0">
          @php $colors = ['#6366f1','#f59e0b','#10b981','#ef4444','#8b5cf6']; $si = 0; @endphp
          @forelse($data['top_staff'] ?? [] as $staff)
          <div class="staff-row">
            <div class="staff-rank" style="background:{{ $colors[$si % 5] }}">{{ $si + 1 }}</div>
            <div class="flex-grow-1">
              <div class="fw-semibold">{{ $staff->staff->full_name ?? ('Staff #' . $staff->staff_id) }}</div>
              <div class="small text-muted">{{ $staff->total_bookings }} bookings</div>
            </div>
            <div class="text-end">
              <div class="fw-semibold text-success">{{ \Currency::format($staff->total_revenue ?? 0) }}</div>
            </div>
          </div>
          @php $si++; @endphp
          @empty
          <div class="p-4 text-center text-muted">
            <i class="fa-solid fa-user-slash fa-2x mb-2 d-block opacity-50"></i>
            No staff data for this period
          </div>
          @endforelse
        </div>
        <div class="card-footer bg-transparent">
          <a href="{{ route('backend.reports.staff-report') }}" class="btn btn-sm btn-outline-info w-100">Full Staff Report</a>
        </div>
      </div>
    </div>

  </div>{{-- /ROW 4 --}}

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- ROW 5: FINANCIAL SUMMARY CARDS + QUICK LINKS               --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div class="row g-3 mb-4">
    <div class="col-xl-8">
      <div class="card exec-card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Financial Summary</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="fin-summary-card services-card">
                <div class="fin-icon"><i class="fa-solid fa-scissors"></i></div>
                <div class="fin-value">{{ \Currency::format($revenueBreakdown['services'] ?? 0) }}</div>
                <div class="fin-label">Services Revenue</div>
                @php $sp = $totalRevBreakdown > 0 ? round((($revenueBreakdown['services'] ?? 0) / $totalRevBreakdown) * 100) : 0; @endphp
                <div class="fin-bar"><div class="fin-bar-fill" style="width:{{ $sp }}%;background:#6366f1;"></div></div>
                <small class="text-muted">{{ $sp }}% of total</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="fin-summary-card packages-card">
                <div class="fin-icon"><i class="fa-solid fa-box"></i></div>
                <div class="fin-value">{{ \Currency::format($revenueBreakdown['packages'] ?? 0) }}</div>
                <div class="fin-label">Packages Revenue</div>
                @php $pp = $totalRevBreakdown > 0 ? round((($revenueBreakdown['packages'] ?? 0) / $totalRevBreakdown) * 100) : 0; @endphp
                <div class="fin-bar"><div class="fin-bar-fill" style="width:{{ $pp }}%;background:#f59e0b;"></div></div>
                <small class="text-muted">{{ $pp }}% of total</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="fin-summary-card products-card">
                <div class="fin-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                <div class="fin-value">{{ \Currency::format($revenueBreakdown['products'] ?? 0) }}</div>
                <div class="fin-label">Product Sales</div>
                @php $prp = $totalRevBreakdown > 0 ? round((($revenueBreakdown['products'] ?? 0) / $totalRevBreakdown) * 100) : 0; @endphp
                <div class="fin-bar"><div class="fin-bar-fill" style="width:{{ $prp }}%;background:#10b981;"></div></div>
                <small class="text-muted">{{ $prp }}% of total</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="fin-summary-card giftcards-card">
                <div class="fin-icon"><i class="fa-solid fa-gift"></i></div>
                <div class="fin-value">{{ \Currency::format($revenueBreakdown['giftcards'] ?? 0) }}</div>
                <div class="fin-label">Gift Cards</div>
                @php $gp = $totalRevBreakdown > 0 ? round((($revenueBreakdown['giftcards'] ?? 0) / $totalRevBreakdown) * 100) : 0; @endphp
                <div class="fin-bar"><div class="fin-bar-fill" style="width:{{ $gp }}%;background:#ec4899;"></div></div>
                <small class="text-muted">{{ $gp }}% of total</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Quick Report Links --}}
    <div class="col-xl-4">
      <div class="card exec-card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fa-solid fa-rocket text-primary me-2"></i>Quick Access — Reports</h5>
        </div>
        <div class="card-body p-2">
          <div class="row g-2">
            @php
              $quickLinks = [
                ['route' => 'backend.reports.sales-by-date',               'icon' => 'fa-chart-line',          'color' => '#6366f1', 'label' => 'Sales by Date'],
                ['route' => 'backend.reports.financial-report',            'icon' => 'fa-calculator',           'color' => '#10b981', 'label' => 'Financial Report'],
                ['route' => 'backend.reports.daily-booking-report',        'icon' => 'fa-file-invoice-dollar',  'color' => '#f59e0b', 'label' => 'Daily Bookings'],
                ['route' => 'backend.reports.overall-booking-report',      'icon' => 'fa-calendar-check',       'color' => '#3b82f6', 'label' => 'Overall Bookings'],
                ['route' => 'backend.reports.staff-report',                'icon' => 'fa-clipboard-user',       'color' => '#8b5cf6', 'label' => 'Staff Report'],
                ['route' => 'backend.reports.payout-report',               'icon' => 'fa-hand-holding-dollar',  'color' => '#ec4899', 'label' => 'Payout Report'],
                ['route' => 'backend.reports.payment-transactions-report', 'icon' => 'fa-credit-card',          'color' => '#06b6d4', 'label' => 'Transactions'],
                ['route' => 'backend.reports.order-report',                'icon' => 'fa-box-open',             'color' => '#84cc16', 'label' => 'Order Report'],
              ];
            @endphp
            @foreach($quickLinks as $ql)
            <div class="col-6">
              <a href="{{ route($ql['route']) }}" class="quick-link-card" style="--ql-color:{{ $ql['color'] }}">
                <i class="fa-solid {{ $ql['icon'] }}"></i>
                <span>{{ $ql['label'] }}</span>
              </a>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>{{-- /ROW 5 --}}

</div>{{-- /exec-dashboard --}}
@endsection

@push('after-styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.css">
<style>
/* ─── Executive Dashboard Styles ────────────────────────────── */
.exec-dashboard { padding-bottom: 2rem; }
.exec-title { font-size: 1.5rem; background: linear-gradient(135deg, var(--bs-primary, #6366f1), #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

/* KPI Cards */
.kpi-card {
  position: relative; border-radius: 16px; padding: 1.25rem 1rem 1rem;
  overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,.06);
  border: 1px solid rgba(255,255,255,.2); transition: transform .2s, box-shadow .2s;
  background: #fff; min-height: 130px;
}
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,.12); }
.kpi-link { position: absolute; inset: 0; }
.kpi-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; margin-bottom: .7rem;
}
.kpi-value { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
.kpi-label { font-size: .75rem; color: #6b7280; margin-top: .2rem; }
.kpi-trend { font-size: .72rem; margin-top: .3rem; font-weight: 600; }
.kpi-trend.up { color: #10b981; }
.kpi-trend.down { color: #ef4444; }
.kpi-trend.neutral { color: #6b7280; }
.kpi-sub { font-size: .72rem; color: #6b7280; margin-top: .25rem; }

.kpi-appointments { border-top: 4px solid #6366f1; }
.kpi-appointments .kpi-icon { background: #ede9fe; color: #6366f1; }
.kpi-revenue { border-top: 4px solid #10b981; }
.kpi-revenue .kpi-icon { background: #d1fae5; color: #10b981; }
.kpi-commission { border-top: 4px solid #f59e0b; }
.kpi-commission .kpi-icon { background: #fef3c7; color: #f59e0b; }
.kpi-customers { border-top: 4px solid #3b82f6; }
.kpi-customers .kpi-icon { background: #dbeafe; color: #3b82f6; }
.kpi-orders { border-top: 4px solid #8b5cf6; }
.kpi-orders .kpi-icon { background: #ede9fe; color: #8b5cf6; }
.kpi-giftcards { border-top: 4px solid #ec4899; }
.kpi-giftcards .kpi-icon { background: #fce7f3; color: #ec4899; }

/* Exec Cards */
.exec-card { border-radius: 16px; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 2px 12px rgba(0,0,0,.05); }
.exec-card .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,.06); padding: 1rem 1.25rem; }
.exec-card .card-footer { border-top: 1px solid rgba(0,0,0,.06); padding: .75rem 1.25rem; }

/* Booking status legend */
.booking-status-legend { font-size: .8rem; }
.badge-status { padding: .2rem .5rem; border-radius: 50px; font-size: .75rem; }
.badge-status.completed { color: #10b981; }
.badge-status.confirmed { color: #3b82f6; }
.badge-status.pending { color: #f59e0b; }
.badge-status.cancelled { color: #ef4444; }

/* Revenue source badges */
.src-badge { padding: .5rem; border-radius: 10px; background: #f9fafb; }
.src-val { font-size: .78rem; font-weight: 700; }
.src-lbl { font-size: .68rem; color: #6b7280; }
.src-services { border-top: 3px solid #6366f1; }
.src-packages { border-top: 3px solid #f59e0b; }
.src-products { border-top: 3px solid #10b981; }
.src-gifts { border-top: 3px solid #ec4899; }

/* Upcoming list */
.upcoming-list { max-height: 340px; overflow-y: auto; }
.upcoming-item {
  display: flex; align-items: center; gap: .75rem; padding: .75rem 1.25rem;
  border-bottom: 1px solid rgba(0,0,0,.05); transition: background .15s;
}
.upcoming-item:last-child { border-bottom: none; }
.upcoming-item:hover { background: #f8faff; }
.avatar-sm { width: 40px; height: 40px; object-fit: cover; }
.upcoming-info .fw-semibold { font-size: .88rem; }

/* Staff rows */
.staff-row {
  display: flex; align-items: center; gap: .75rem;
  padding: .75rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.05);
  transition: background .15s;
}
.staff-row:last-child { border-bottom: none; }
.staff-row:hover { background: #f8faff; }
.staff-rank {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 700; font-size: .8rem; flex-shrink: 0;
}

/* Executive table */
.exec-table th { font-size: .78rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; border-bottom-width: 1px; background: #f9fafb; }
.exec-table td { font-size: .85rem; vertical-align: middle; }

/* KPI mini */
.kpi-mini-row { display: flex; gap: 1rem; }
.kpi-mini { display: flex; align-items: center; gap: .5rem; flex: 1; }
.kpi-mini i { font-size: 1.2rem; }

/* Financial summary */
.fin-summary-card { background: #fff; border: 1px solid rgba(0,0,0,.07); border-radius: 14px; padding: 1rem; text-align: center; height: 100%; }
.fin-icon { font-size: 1.4rem; margin-bottom: .4rem; color: #6366f1; }
.fin-value { font-size: 1rem; font-weight: 700; }
.fin-label { font-size: .72rem; color: #6b7280; margin-bottom: .5rem; }
.fin-bar { height: 5px; background: #f1f5f9; border-radius: 99px; margin: .4rem 0 .2rem; overflow: hidden; }
.fin-bar-fill { height: 100%; border-radius: 99px; transition: width .8s ease; }

/* Quick links */
.quick-link-card {
  display: flex; align-items: center; gap: .5rem;
  padding: .6rem .8rem; border-radius: 10px;
  background: rgba(var(--ql-color-rgb, 99,102,241),.07);
  border: 1px solid rgba(var(--ql-color-rgb, 99,102,241),.15);
  color: var(--ql-color); font-size: .8rem; font-weight: 600;
  text-decoration: none; transition: all .2s;
  border-left: 3px solid var(--ql-color);
}
.quick-link-card:hover { transform: translateX(3px); background: var(--ql-color); color: #fff; }
.quick-link-card i { font-size: 1rem; }

/* Packages card specific */
.packages-card .fin-icon { color: #f59e0b; }
.products-card .fin-icon { color: #10b981; }
.giftcards-card .fin-icon { color: #ec4899; }
</style>
@endpush

@push('after-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"
  integrity="sha512-Kr1p/vGF2i84dZQTkoYZ2do8xHRaiqIa7ysnDugwoOcG0SbIx98erNekP/qms/hBDiBxj336//77d0dv53Jmew=="
  crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
$(document).ready(function () {

  /* ── Flatpickr date range ── */
  const range_flatpicker = document.querySelectorAll('.dashboard-date-range');
  Array.from(range_flatpicker, (elem) => {
    if (typeof flatpickr !== typeof undefined) {
      flatpickr(elem, { mode: "range" });
    }
  });

  /* ── Scroll for upcoming list ── */
  if (typeof Scrollbar !== 'undefined' && document.querySelector('.upcoming-list')) {
    Scrollbar.init(document.querySelector('.upcoming-list'), { continuousScrolling: false });
  }

  const variableColors = (typeof IQUtils !== 'undefined') ? IQUtils.getVariableColor() : {};
  const primaryColor   = variableColors.primary   || '#6366f1';
  const secondaryColor = variableColors.secondary || '#f59e0b';

  /* ── CHART 1: Main Revenue Trend (Area) ── */
  const trendData   = @json($data['revenue_chart']['total_price'] ?? []);
  const trendDates  = @json($data['revenue_chart']['xaxis'] ?? []);

  if (document.querySelector('#chart-main-trend')) {
    new ApexCharts(document.querySelector('#chart-main-trend'), {
      series: [{ name: 'Revenue', data: trendData }],
      chart: { type: 'area', height: 300, toolbar: { show: false }, sparkline: { enabled: false } },
      colors: ['#6366f1'],
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] } },
      stroke: { curve: 'smooth', width: 3 },
      dataLabels: { enabled: false },
      xaxis: { categories: trendDates, labels: { style: { fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { style: { fontSize: '11px' }, formatter: (v) => v.toFixed(0) }, tickAmount: 4 },
      grid: { strokeDashArray: 5, borderColor: '#f1f5f9' },
      tooltip: { y: { formatter: (v) => parseFloat(v || 0).toFixed(2) } },
    }).render();
  }

  /* ── CHART 2: Booking Status Donut ── */
  const bsCompleted = {{ $bookingStatus['completed'] }};
  const bsPending   = {{ $bookingStatus['pending'] }};
  const bsCancelled = {{ $bookingStatus['cancelled'] }};
  const bsConfirmed = {{ $bookingStatus['confirmed'] }};

  if (document.querySelector('#chart-booking-status')) {
    new ApexCharts(document.querySelector('#chart-booking-status'), {
      series: [bsCompleted, bsConfirmed, bsPending, bsCancelled],
      labels: ['Completed', 'Confirmed', 'Pending', 'Cancelled'],
      colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
      chart: { type: 'donut', height: 240 },
      legend: { show: false },
      plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '13px' } } } } },
      dataLabels: { enabled: false },
    }).render();
  }

  /* ── CHART 3: Revenue by Source (Horizontal bar) ── */
  const rbLabels = ['Services', 'Packages', 'Products', 'Gift Cards'];
  const rbData   = [
    {{ $revenueBreakdown['services']  ?? 0 }},
    {{ $revenueBreakdown['packages']  ?? 0 }},
    {{ $revenueBreakdown['products']  ?? 0 }},
    {{ $revenueBreakdown['giftcards'] ?? 0 }},
  ];

  if (document.querySelector('#chart-revenue-breakdown')) {
    new ApexCharts(document.querySelector('#chart-revenue-breakdown'), {
      series: [{ name: 'Revenue', data: rbData }],
      chart: { type: 'bar', height: 220, toolbar: { show: false } },
      colors: ['#6366f1', '#f59e0b', '#10b981', '#ec4899'],
      plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 6, barHeight: '60%' } },
      dataLabels: { enabled: true, formatter: (v) => parseFloat(v).toFixed(2) },
      xaxis: { categories: rbLabels, labels: { style: { fontSize: '11px' } } },
      yaxis: { labels: { style: { fontSize: '12px' } } },
      legend: { show: false },
      grid: { strokeDashArray: 4, borderColor: '#f1f5f9' },
    }).render();
  }

  /* ── CHART 4: Cancellation Rate (Radial) ── */
  const cancellationRate = {{ $data['cancellation_rate'] ?? 0 }};

  if (document.querySelector('#chart-cancellation-rate')) {
    new ApexCharts(document.querySelector('#chart-cancellation-rate'), {
      series: [cancellationRate],
      chart: { type: 'radialBar', height: 200 },
      colors: [cancellationRate > 30 ? '#ef4444' : cancellationRate > 15 ? '#f59e0b' : '#10b981'],
      plotOptions: {
        radialBar: {
          hollow: { size: '60%' },
          dataLabels: {
            name: { fontSize: '13px', offsetY: -10 },
            value: { fontSize: '1.5rem', fontWeight: 700, formatter: (v) => v + '%' },
          }
        }
      },
      labels: ['Cancelled'],
    }).render();
  }

  /* ── CHART 5: Appointments vs Revenue (Mixed) ── */
  const bookingsData  = @json($data['revenue_chart']['total_bookings'] ?? []);
  const revenueData   = @json($data['revenue_chart']['total_price'] ?? []);
  const chartDates    = @json($data['revenue_chart']['xaxis'] ?? []);

  if (document.querySelector('#chart-appt-vs-revenue')) {
    new ApexCharts(document.querySelector('#chart-appt-vs-revenue'), {
      series: [
        { name: 'Revenue', type: 'area', data: revenueData },
        { name: 'Appointments', type: 'column', data: bookingsData }
      ],
      colors: ['#6366f1', '#f59e0b'],
      chart: { type: 'line', height: 280, toolbar: { show: false } },
      stroke: { width: [3, 0], curve: 'smooth' },
      fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: .35, opacityTo: .05 } },
      dataLabels: { enabled: false },
      xaxis: { categories: chartDates, labels: { style: { fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: [
        { title: { text: 'Revenue', style: { fontSize: '11px' } }, labels: { style: { fontSize: '11px' }, formatter: v => parseFloat(v||0).toFixed(0) }, tickAmount: 4 },
        { opposite: true, title: { text: 'Bookings', style: { fontSize: '11px' } }, labels: { style: { fontSize: '11px' } }, tickAmount: 4, min: 0 }
      ],
      legend: { position: 'top', fontSize: '12px' },
      grid: { strokeDashArray: 5, borderColor: '#f1f5f9' },
    }).render();
  }

}); // end ready
</script>
@endpush
