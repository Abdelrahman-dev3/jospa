@extends('backend.layouts.app')

@section('title') {{ __('report.title_sales_report') }} @endsection

@section('content')
<style>
    .woo-reports-wrapper {
        font-family: inherit;
    }
    .woo-nav-tabs {
        border-bottom: 2px solid #ddd;
        background: #f8f9fa;
        padding: 8px 15px 0;
        border-radius: 8px 8px 0 0;
    }
    .woo-nav-tabs .nav-link {
        color: #555;
        font-weight: 600;
        border: 1px solid transparent;
        border-bottom: none;
        padding: 8px 20px;
        margin-bottom: -2px;
        border-radius: 6px 6px 0 0;
    }
    .woo-nav-tabs .nav-link.active {
        background: #fff;
        border-color: #ddd #ddd #fff;
        color: #0073aa;
    }
    .woo-subnav {
        background: #fff;
        padding: 10px 15px;
        border-bottom: 1px solid #e5e5e5;
        display: flex;
        gap: 15px;
        font-size: 14px;
    }
    .woo-subnav a {
        color: #0073aa;
        text-decoration: none;
    }
    .woo-subnav a.active {
        color: #000;
        font-weight: bold;
    }
    .woo-filter-bar {
        background: #fff;
        padding: 12px 15px;
        border-bottom: 1px solid #e5e5e5;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .woo-presets-group .btn-preset {
        background: #f7f7f7;
        border: 1px solid #cccccc;
        color: #555;
        font-size: 13px;
        padding: 5px 12px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .woo-presets-group .btn-preset.active,
    .woo-presets-group .btn-preset:hover {
        background: #0073aa;
        border-color: #0073aa;
        color: #fff;
    }
    .woo-summary-card {
        border-left: 4px solid #0073aa;
        padding: 12px 15px;
        background: #fdfdfd;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 10px;
    }
    .woo-summary-card.net-sales { border-left-color: #2ea2cc; }
    .woo-summary-card.orders { border-left-color: #46b450; }
    .woo-summary-card.items { border-left-color: #11a0d2; }
    .woo-summary-card.refunds { border-left-color: #d63638; }
    .woo-summary-card.shipping { border-left-color: #00a0d2; }
    .woo-summary-card.coupons { border-left-color: #f56e28; }
    .woo-summary-value {
        font-size: 20px;
        font-weight: bold;
        color: #23282d;
    }
    .woo-summary-label {
        font-size: 13px;
        color: #666;
    }
    .woo-chart-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        min-height: 380px;
    }
</style>

<div class="woo-reports-wrapper card">
    <!-- Header Notice Banner -->
    <div class="alert alert-warning mb-0 rounded-0 border-0 fs-13 py-2 px-3">
        <strong>WooCommerce Reports:</strong> {{ __('report.title_sales_report') }}
    </div>

    <!-- Main Navigation Tabs -->
    <div class="woo-nav-tabs d-flex">
        <a href="{{ route('backend.reports.sales-by-date') }}" class="nav-link active">
            {{ __('report.orders_tab') }}
        </a>
        <a href="{{ route('backend.customers.index') }}" class="nav-link">
            {{ __('report.customers_tab') }}
        </a>
        <a href="{{ route('backend.services.index') }}" class="nav-link">
            {{ __('report.stock_tab') }}
        </a>
    </div>

    <!-- Sub Navigation -->
    <div class="woo-subnav">
        <a href="{{ route('backend.reports.sales-by-date') }}" class="active">{{ __('report.sales_by_date') }}</a>
        <span class="text-muted">|</span>
        <a href="{{ route('backend.reports.order-report') }}">{{ __('report.order-report') }}</a>
        <span class="text-muted">|</span>
        <a href="{{ route('backend.reports.coupon-report') }}">{{ __('report.coupons_by_date') }}</a>
    </div>

    <!-- Date Preset & Filter Bar -->
    <div class="woo-filter-bar">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <div class="woo-presets-group d-flex gap-1" id="preset-buttons">
                <button type="button" class="btn btn-preset" data-preset="this_year">{{ __('report.year_preset') }}</button>
                <button type="button" class="btn btn-preset" data-preset="last_month">{{ __('report.last_month_preset') }}</button>
                <button type="button" class="btn btn-preset active" data-preset="this_month">{{ __('report.this_month_preset') }}</button>
                <button type="button" class="btn btn-preset" data-preset="last_7_days">{{ __('report.last_7_days_preset') }}</button>
            </div>

            <div class="d-flex align-items-center gap-1 ms-2">
                <span class="fs-13 text-muted">{{ __('report.custom_date_preset') }}:</span>
                <input type="text" id="custom_date_range" class="form-control form-control-sm" style="width: 210px;" placeholder="yyyy-mm-dd - yyyy-mm-dd" readonly />
                <button type="button" id="btn-apply-custom" class="btn btn-sm btn-primary">{{ __('report.apply_filter') }}</button>
            </div>
        </div>

        <div>
            <a href="#" id="btn-export-csv" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-download me-1"></i> {{ __('report.export_csv') }}
            </a>
        </div>
    </div>

    <!-- Body Layout: Metrics Sidebar + Chart & DataTable -->
    <div class="card-body">
        <div class="row g-3">
            <!-- Sidebar Metrics (Right in RTL, Left in LTR) -->
            <div class="col-lg-3 col-md-4 order-lg-2">
                <div class="woo-summary-card">
                    <div class="woo-summary-value" id="val-gross-sales">0.00</div>
                    <div class="woo-summary-label">{{ __('report.gross_sales_period') }}</div>
                </div>

                <div class="woo-summary-card net-sales">
                    <div class="woo-summary-value" id="val-net-sales">0.00</div>
                    <div class="woo-summary-label">{{ __('report.net_sales_period') }}</div>
                </div>

                <div class="woo-summary-card orders">
                    <div class="woo-summary-value" id="val-orders-count">0</div>
                    <div class="woo-summary-label">{{ __('report.orders_placed') }}</div>
                </div>

                <div class="woo-summary-card items">
                    <div class="woo-summary-value" id="val-items-count">0</div>
                    <div class="woo-summary-label">{{ __('report.items_purchased') }}</div>
                </div>

                <div class="woo-summary-card refunds">
                    <div class="woo-summary-value" id="val-refunds-amount">0.00</div>
                    <div class="woo-summary-label">{{ __('report.refunded_amount') }}</div>
                </div>

                <div class="woo-summary-card shipping">
                    <div class="woo-summary-value" id="val-shipping-cost">0.00</div>
                    <div class="woo-summary-label">{{ __('report.shipping_cost') }}</div>
                </div>

                <div class="woo-summary-card coupons">
                    <div class="woo-summary-value" id="val-coupons-used">0.00</div>
                    <div class="woo-summary-label">{{ __('report.coupons_used_value') }}</div>
                </div>
            </div>

            <!-- Chart & Data Table Area -->
            <div class="col-lg-9 col-md-8 order-lg-1">
                <div class="woo-chart-card mb-4">
                    <div id="sales-line-chart" style="height: 350px;"></div>
                </div>

                <div class="table-responsive">
                    <table id="datatable-sales" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>{{ __('report.lbl_date') }}</th>
                                <th>{{ __('report.orders_placed') }}</th>
                                <th>{{ __('report.items_purchased') }}</th>
                                <th>{{ __('report.gross_sales_period') }}</th>
                                <th>{{ __('report.net_sales_period') }}</th>
                                <th>{{ __('report.shipping_cost') }}</th>
                                <th>{{ __('report.coupons_used_value') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-styles')
<link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.css">
@endpush

@push('after-scripts')
<script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"></script>

<script type="text/javascript">
    let currentPreset = 'this_month';
    let currentDateRange = '';
    let salesChart = null;
    let salesDataTable = null;

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr('#custom_date_range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                onChange: function(selectedDates, dateStr) {
                    currentDateRange = dateStr;
                }
            });
        }

        initSalesChart();
        loadSalesData();

        // Preset buttons click handler
        document.querySelectorAll('#preset-buttons .btn-preset').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#preset-buttons .btn-preset').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentPreset = this.getAttribute('data-preset');
                document.getElementById('custom_date_range').value = '';
                currentDateRange = '';
                loadSalesData();
            });
        });

        // Apply custom date range click handler
        document.getElementById('btn-apply-custom').addEventListener('click', function() {
            const val = document.getElementById('custom_date_range').value;
            if (val) {
                document.querySelectorAll('#preset-buttons .btn-preset').forEach(b => b.classList.remove('active'));
                currentPreset = 'custom';
                currentDateRange = val;
                loadSalesData();
            }
        });

        // Export CSV button handler
        document.getElementById('btn-export-csv').addEventListener('click', function(e) {
            e.preventDefault();
            let exportUrl = "{{ route('backend.reports.sales-by-date.export') }}?preset=" + currentPreset;
            if (currentDateRange) {
                exportUrl += "&date_range=" + encodeURIComponent(currentDateRange);
            }
            window.location.href = exportUrl;
        });
    });

    function initSalesChart() {
        const options = {
            chart: {
                type: 'line',
                height: 350,
                toolbar: { show: true },
                zoom: { enabled: false }
            },
            stroke: {
                curve: 'smooth',
                width: [3, 3]
            },
            colors: ['#0073aa', '#46b450'],
            series: [
                { name: "{{ __('report.gross_sales_period') }}", data: [] },
                { name: "{{ __('report.net_sales_period') }}", data: [] }
            ],
            xaxis: { categories: [] },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return val ? val.toFixed(2) : '0.00';
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val ? val.toFixed(2) : '0.00';
                    }
                }
            }
        };

        salesChart = new ApexCharts(document.querySelector("#sales-line-chart"), options);
        salesChart.render();
    }

    function loadSalesData() {
        $.ajax({
            url: "{{ route('backend.reports.sales-by-date.index_data') }}",
            type: 'GET',
            data: {
                preset: currentPreset,
                date_range: currentDateRange
            },
            success: function(response) {
                if (response.summary) {
                    $('#val-gross-sales').text(response.summary.gross_sales_formatted);
                    $('#val-net-sales').text(response.summary.net_sales_formatted);
                    $('#val-orders-count').text(response.summary.orders_count);
                    $('#val-items-count').text(response.summary.items_count);
                    $('#val-refunds-amount').text(response.summary.refund_amount_formatted);
                    $('#val-shipping-cost').text(response.summary.shipping_cost_formatted);
                    $('#val-coupons-used').text(response.summary.coupons_used_formatted);
                }

                if (response.chart && salesChart) {
                    salesChart.updateOptions({
                        xaxis: { categories: response.chart.categories },
                        series: [
                            { name: "{{ __('report.gross_sales_period') }}", data: response.chart.gross_sales },
                            { name: "{{ __('report.net_sales_period') }}", data: response.chart.net_sales }
                        ]
                    });
                }

                if (response.table_rows) {
                    renderDataTable(response.table_rows);
                }
            }
        });
    }

    function renderDataTable(rows) {
        if (salesDataTable) {
            salesDataTable.clear().destroy();
        }

        salesDataTable = $('#datatable-sales').DataTable({
            data: rows,
            columns: [
                { data: 'date' },
                { data: 'orders_count' },
                { data: 'items_count' },
                { data: 'gross_sales' },
                { data: 'net_sales' },
                { data: 'shipping_cost' },
                { data: 'coupons_value' }
            ],
            order: [[0, 'desc']],
            paging: true,
            searching: false,
            info: false,
            language: {
                emptyTable: "{{ __('messages.no_data_found') }}"
            }
        });
    }
</script>
@endpush
