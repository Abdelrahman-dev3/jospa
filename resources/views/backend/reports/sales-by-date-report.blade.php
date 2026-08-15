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
        padding: 14px 18px;
        border-bottom: 1px solid #e5e5e5;
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
    .filter-section-box {
        background: #fbfbfb;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 15px;
        margin-top: 12px;
    }
    .filter-label {
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 4px;
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
    .select2-container--default .select2-selection--single {
        height: 34px !important;
        padding: 2px 5px !important;
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px !important;
    }
</style>

<div class="woo-reports-wrapper card">
    <!-- Header Notice Banner -->
    <div class="alert alert-info mb-0 rounded-0 border-0 fs-13 py-2 px-3">
        <strong>{{ __('report.title_sales_report') }}</strong>
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
        <div class="d-flex align-items-center flex-wrap justify-content-between gap-2">
            <!-- Left: Preset Buttons & Custom Date Range -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="woo-presets-group d-flex gap-1" id="preset-buttons">
                    <button type="button" class="btn btn-preset" data-preset="this_year">{{ __('report.year_preset') }}</button>
                    <button type="button" class="btn btn-preset" data-preset="last_month">{{ __('report.last_month_preset') }}</button>
                    <button type="button" class="btn btn-preset active" data-preset="this_month">{{ __('report.this_month_preset') }}</button>
                    <button type="button" class="btn btn-preset" data-preset="last_7_days">{{ __('report.last_7_days_preset') }}</button>
                </div>

                <div class="d-flex align-items-center gap-1 ms-1">
                    <span class="fs-13 text-muted">{{ __('report.custom_date_preset') }}:</span>
                    <input type="text" id="custom_date_range" class="form-control form-control-sm" style="width: 200px;" placeholder="yyyy-mm-dd - yyyy-mm-dd" readonly />
                </div>
            </div>

            <!-- Right: Export CSV & Toggle Advanced Filters -->
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-toggle-filters">
                    <i class="fa fa-filter me-1"></i> {{ __('report.advanced_filters') }}
                </button>
                <a href="#" id="btn-export-csv" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-download me-1"></i> {{ __('report.export_csv') }}
                </a>
            </div>
        </div>

        <!-- Advanced Filter Options Container -->
        <div class="filter-section-box" id="advanced-filter-panel">
            <div class="row g-2">
                <!-- Branch Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="filter-label"><i class="fa fa-building me-1 text-primary"></i> {{ __('report.filter_branch') }}</label>
                    <select id="filter_branch_id" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_branches') }}</option>
                        @if(isset($branches))
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Category Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="filter-label"><i class="fa fa-layer-group me-1 text-info"></i> {{ __('report.filter_category') }}</label>
                    <select id="filter_category_id" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_categories') }}</option>
                        @if(isset($categories))
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Service Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="filter-label"><i class="fa fa-spa me-1 text-success"></i> {{ __('report.filter_service') }}</label>
                    <select id="filter_service_id" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_services') }}</option>
                        @if(isset($services))
                            @foreach($services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Employee / Staff Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="filter-label"><i class="fa fa-user-tie me-1 text-warning"></i> {{ __('report.filter_employee') }}</label>
                    <select id="filter_employee_id" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_employees') }}</option>
                        @if(isset($employees))
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Customer Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="filter-label"><i class="fa fa-user me-1 text-secondary"></i> {{ __('report.filter_customer') }}</label>
                    <select id="filter_customer_id" class="form-control form-control-sm select2-ajax" data-placeholder="{{ __('report.search_customer_placeholder') }}">
                        <option value="">{{ __('report.all_customers') }}</option>
                    </select>
                </div>

                <!-- Payment Method Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="filter-label"><i class="fa fa-credit-card me-1 text-dark"></i> {{ __('report.filter_payment_method') }}</label>
                    <select id="filter_payment_method" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_payment_methods') }}</option>
                        <option value="cash">{{ __('report.payment_cash') }}</option>
                        <option value="urpay">UrPay</option>
                        <option value="card">Card / مدى</option>
                        <option value="wallet">{{ __('report.payment_wallet') }}</option>
                        <option value="tabby">Tabby</option>
                        <option value="tamara">Tamara</option>
                    </select>
                </div>

                <!-- Booking Status Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="filter-label"><i class="fa fa-info-circle me-1 text-primary"></i> {{ __('report.filter_status') }}</label>
                    <select id="filter_booking_status" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_statuses') }}</option>
                        <option value="completed">{{ __('report.status_completed') }}</option>
                        <option value="pending">{{ __('report.status_pending') }}</option>
                        <option value="confirmed">{{ __('report.status_confirmed') }}</option>
                        <option value="cancelled">{{ __('report.status_cancelled') }}</option>
                    </select>
                </div>

                <!-- Service Type Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="filter-label"><i class="fa fa-tags me-1 text-danger"></i> {{ __('report.filter_service_type') }}</label>
                    <select id="filter_service_type" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_service_types') }}</option>
                        <option value="salon">{{ __('report.type_salon') }}</option>
                        <option value="home">{{ __('report.type_home') }}</option>
                        <option value="gift_card">{{ __('report.type_gift_card') }}</option>
                        <option value="package">{{ __('report.type_package') }}</option>
                    </select>
                </div>

                <!-- Coupon Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="filter-label"><i class="fa fa-ticket me-1 text-success"></i> {{ __('report.filter_coupon') }}</label>
                    <select id="filter_has_coupon" class="form-control form-control-sm select2">
                        <option value="">{{ __('report.all_coupon_options') }}</option>
                        <option value="yes">{{ __('report.coupon_applied_only') }}</option>
                        <option value="no">{{ __('report.no_coupon_only') }}</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                <button type="button" id="btn-reset-filters" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-redo me-1"></i> {{ __('report.btn_reset_filters') }}
                </button>
                <button type="button" id="btn-apply-filters" class="btn btn-sm btn-primary px-3">
                    <i class="fa fa-search me-1"></i> {{ __('report.btn_apply_filters') }}
                </button>
            </div>
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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@push('after-scripts')
<script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script type="text/javascript">
    let currentPreset = 'this_month';
    let currentDateRange = '';
    let salesChart = null;
    let salesDataTable = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Flatpickr
        if (typeof flatpickr !== 'undefined') {
            flatpickr('#custom_date_range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                onChange: function(selectedDates, dateStr) {
                    currentDateRange = dateStr;
                    if (dateStr) {
                        document.querySelectorAll('#preset-buttons .btn-preset').forEach(b => b.classList.remove('active'));
                        currentPreset = 'custom';
                        loadSalesData();
                    }
                }
            });
        }

        // Initialize Select2 dropdowns
        $('.select2').select2({
            width: '100%',
            allowClear: true
        });

        // Initialize Customer Select2 with AJAX search
        $('#filter_customer_id').select2({
            width: '100%',
            allowClear: true,
            placeholder: "{{ __('report.search_customer_placeholder') }}",
            ajax: {
                url: "{{ route('backend.get_search_data') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        type: 'customers',
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || []
                    };
                },
                cache: true
            }
        });

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

        // Apply filters button handler
        document.getElementById('btn-apply-filters').addEventListener('click', function() {
            loadSalesData();
        });

        // Reset filters button handler
        document.getElementById('btn-reset-filters').addEventListener('click', function() {
            $('#filter_branch_id').val('').trigger('change');
            $('#filter_category_id').val('').trigger('change');
            $('#filter_service_id').val('').trigger('change');
            $('#filter_employee_id').val('').trigger('change');
            $('#filter_customer_id').val(null).trigger('change');
            $('#filter_payment_method').val('').trigger('change');
            $('#filter_booking_status').val('').trigger('change');
            $('#filter_service_type').val('').trigger('change');
            $('#filter_has_coupon').val('').trigger('change');
            loadSalesData();
        });

        // Toggle advanced filters panel
        document.getElementById('btn-toggle-filters').addEventListener('click', function() {
            $('#advanced-filter-panel').slideToggle(200);
        });

        // Export CSV button handler
        document.getElementById('btn-export-csv').addEventListener('click', function(e) {
            e.preventDefault();
            let filterParams = getFilterParams();
            let queryStr = $.param(filterParams);
            let exportUrl = "{{ route('backend.reports.sales-by-date.export') }}?" + queryStr;
            window.location.href = exportUrl;
        });
    });

    function getFilterParams() {
        return {
            preset: currentPreset,
            date_range: currentDateRange,
            branch_id: $('#filter_branch_id').val() || '',
            category_id: $('#filter_category_id').val() || '',
            service_id: $('#filter_service_id').val() || '',
            employee_id: $('#filter_employee_id').val() || '',
            customer_id: $('#filter_customer_id').val() || '',
            payment_method: $('#filter_payment_method').val() || '',
            booking_status: $('#filter_booking_status').val() || '',
            service_type: $('#filter_service_type').val() || '',
            has_coupon: $('#filter_has_coupon').val() || ''
        };
    }

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
        const payload = getFilterParams();

        $.ajax({
            url: "{{ route('backend.reports.sales-by-date.index_data') }}",
            type: 'GET',
            data: payload,
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
