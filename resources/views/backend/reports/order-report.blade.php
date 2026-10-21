@extends('backend.layouts.app')

@section('title') {{ __($module_title) }} @endsection

@push('after-styles')
<link rel="stylesheet" href='{{ mix("modules/product/style.css") }}'>
<link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.css" />
<style>
    .report-summary-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.2s ease;
    }
    .report-summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(15, 23, 42, 0.08);
    }
    .report-summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .report-chart-container {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
</style>
@endpush

@section('content')
    @include('backend.reports.report-nav')

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- SUMMARY KPI CARDS                                           --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي عدد الطلبات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-order-count">0 طلب</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-tags"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">القطع المباعة</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-order-items">0 قطعة</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-percent"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي الخصومات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-order-discount">0.00 ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="report-summary-card" style="border-inline-start: 4px solid #059669;">
                <div class="report-summary-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي مبيعات المنتجات</div>
                    <div class="fs-4 fw-bolder text-success" id="stat-order-revenue">0.00 ر.س</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- INTERACTIVE CHART                                           --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="report-chart-container">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i> مسار مبيعات منتجات المتجر</h5>
                <p class="text-muted small mb-0">تحليل الإيرادات المحققة من بيع منتجات العناية والسبا</p>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold">
                مبيعات المنتجات
            </span>
        </div>
        <div id="chart-order-trend" style="min-height: 280px;"></div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- MAIN TABLE & FILTER                                         --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="card mb-3">
        <div class="card-body">
            <x-backend.section-header>
                <div class="row w-100">
                    <div class="col-md-8">
                        <div class="input-group gap-2 flex-nowrap">
                            <input type="text" name="order_date" id="order_date" placeholder="اختر التاريخ" class="order-report-date-range form-control" readonly />
                            <button id="reset" class="btn bg-primary rounded" data-bs-toggle="tooltip" title="إعادة تعيين">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M21.4799 12.2424C21.7557 12.2326 21.9886 12.4482 21.9852 12.7241C21.9595 14.8075 21.2975 16.8392 20.0799 18.5506C18.7652 20.3986 16.8748 21.7718 14.6964 22.4612C12.518 23.1505 10.1711 23.1183 8.01299 22.3694C5.85488 21.6205 4.00382 20.196 2.74167 18.3126C1.47952 16.4293 0.875433 14.1905 1.02139 11.937C1.16734 9.68346 2.05534 7.53876 3.55018 5.82945C5.04501 4.12014 7.06478 2.93987 9.30193 2.46835C11.5391 1.99683 13.8711 2.2599 15.9428 3.2175L16.7558 1.91838C16.9822 1.55679 17.5282 1.62643 17.6565 2.03324L18.8635 5.85986C18.945 6.11851 18.8055 6.39505 18.549 6.48314L14.6564 7.82007C14.2314 7.96603 13.8445 7.52091 14.0483 7.12042L14.6828 5.87345C13.1977 5.18699 11.526 4.9984 9.92231 5.33642C8.31859 5.67443 6.8707 6.52052 5.79911 7.74586C4.72753 8.97119 4.09095 10.5086 3.98633 12.1241C3.8817 13.7395 4.31474 15.3445 5.21953 16.6945C6.12431 18.0446 7.45126 19.0658 8.99832 19.6027C10.5454 20.1395 12.2278 20.1626 13.7894 19.6684C15.351 19.1743 16.7062 18.1899 17.6486 16.8652C18.4937 15.6773 18.9654 14.2742 19.0113 12.8307C19.0201 12.5545 19.2341 12.3223 19.5103 12.3125L21.4799 12.2424Z"
                                        fill="#ffffff"></path>
                                    <path
                                        d="M20.0941 18.5594C21.3117 16.848 21.9736 14.8163 21.9993 12.7329C22.0027 12.4569 21.7699 12.2413 21.4941 12.2512L19.5244 12.3213C19.2482 12.3311 19.0342 12.5633 19.0254 12.8395C18.9796 14.283 18.5078 15.6861 17.6628 16.8739C16.7203 18.1986 15.3651 19.183 13.8035 19.6772C12.2419 20.1714 10.5595 20.1483 9.01246 19.6114C7.4654 19.0746 6.13845 18.0534 5.23367 16.7033C4.66562 15.8557 4.28352 14.9076 4.10367 13.9196C4.00935 18.0934 6.49194 21.37 10.008 22.6416C10.697 22.8908 11.4336 22.9852 12.1652 22.9465C13.075 22.8983 13.8508 22.742 14.7105 22.4699C16.8889 21.7805 18.7794 20.4073 20.0941 18.5594Z"
                                        fill="#ffffff"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="datatable-filter">
                            <select name="payment_status" id="payment_status" class="select2 form-control" data-filter="select">
                                <option value="">حالة الدفع (الكل)</option>
                                <option value="1">مدفوع</option>
                                <option value="0">غير مدفوع</option>
                            </select>
                        </div>
                    </div>
                </div>
            </x-backend.section-header>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="datatable" class="table table-striped border table-responsive">
            </table>
        </div>
    </div>
@endsection

@push('after-scripts')
<script src='{{ mix("modules/product/script.js") }}'></script>
<script src="{{ asset('js/form-offcanvas/index.js') }}" defer></script>
<script src="{{ asset('js/form-modal/index.js') }}" defer></script>
<script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"></script>

<script type="text/javascript">
    let orderReportChart = null;

    function updateOrderReportAnalytics(rows) {
        let totalOrders = 0;
        let totalItems = 0;
        let totalDiscount = 0;
        let totalRevenue = 0;

        let dateMap = {};

        if (rows && rows.length > 0) {
            totalOrders = rows.length;

            rows.forEach(row => {
                const itemsCount = parseInt(row.items || '1', 10) || 1;
                const discAmt    = parseFloat((row.status || '0').replace(/[^0-9.-]+/g, '')) || 0;
                const earnAmt    = parseFloat((row.total_admin_earnings || '0').replace(/[^0-9.-]+/g, '')) || 0;
                const placedOn   = row.placed_on || 'تاريخ';

                totalItems += itemsCount;
                totalDiscount += discAmt;
                totalRevenue += earnAmt;

                if (!dateMap[placedOn]) {
                    dateMap[placedOn] = 0;
                }
                dateMap[placedOn] += earnAmt;
            });
        }

        const currencySymbol = ' ر.س';
        document.getElementById('stat-order-count').innerText = totalOrders.toLocaleString() + ' طلب';
        document.getElementById('stat-order-items').innerText = totalItems.toLocaleString() + ' قطعة';
        document.getElementById('stat-order-discount').innerText = totalDiscount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;
        document.getElementById('stat-order-revenue').innerText = totalRevenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;

        const dates = Object.keys(dateMap).slice(-15);
        const amounts = dates.map(d => +(Math.round(dateMap[d] + "e+2") + "e-2"));

        renderOrderChart(dates, amounts);
    }

    function renderOrderChart(dates, amounts) {
        const chartEl = document.querySelector('#chart-order-trend');
        if (!chartEl || typeof ApexCharts === 'undefined') return;

        const chartOptions = {
            series: [{ name: 'مبيعات المنتجات (ر.س)', data: amounts }],
            chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#059669'],
            stroke: { width: 3, curve: 'smooth' },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            xaxis: { categories: dates, labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: v => Number(v || 0).toLocaleString() + ' ر.س' } },
            dataLabels: { enabled: false },
            grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        };

        if (orderReportChart) {
            orderReportChart.updateOptions(chartOptions);
        } else {
            orderReportChart = new ApexCharts(chartEl, chartOptions);
            orderReportChart.render();
        }
    }

    const range_flatpicker = document.querySelectorAll('.order-report-date-range')

    Array.from(range_flatpicker, (elem) => {
        if (typeof flatpickr !== typeof undefined) {
            flatpickr(elem, {
                mode: "range",
                dateFormat: "d-m-Y",
            })
        }
    })

    $('#reset').on('click', function(e) {
        $('#order_date').val('');
        $('#payment_status').val('').trigger('change');
        window.renderedDataTable.ajax.reload(null, false);
        $('.tooltip').removeClass('show');
    });

    const columns = [
        {
            data: 'DT_RowIndex',
            name: 'DT_RowIndex',
            title: "{{ __('report.lbl_no') }}",
            orderable: false,
            searchable: false,
            width: '5%',
        },
        {
            data: 'customer_name',
            name: 'customer_name',
            title: "{{ __('booking.lbl_customer_name') }}",
            orderable: false,
        },
        {
            data: 'phone',
            name: 'phone',
            title: "{{ __('branch.lbl_contact_number') }}",
            orderable: false,
        },
        {
            data: 'placed_on',
            name: 'placed_on',
            title: "{{ __('messages.placed_on') }}",
            orderable: false,
            searchable: false,
        },
        {
            data: 'items',
            name: 'items',
            title: "{{ __('messages.items') }}",
            orderable: false,
            searchable: false,
        },
        {
            data: 'payment',
            name: 'payment',
            title: "{{ __('messages.payment') }}",
            orderable: false,
            searchable: false,
        },
        {
            data: 'status',
            name: 'status',
            title: "{{ __('messages.discount') }}",
            orderable: false,
            searchable: false,
        },
        {
            data: 'total_admin_earnings',
            name: 'total_admin_earnings',
            title: "{{ __('order.lbl_total_amount') }}",
            orderable: false,
            searchable: false,
        },
    ]

    let finalColumns = [ ...columns ]

    document.addEventListener('DOMContentLoaded', (event) => {
        initDatatable({
            url: '{{ route('backend.reports.order-report.index_data') }}',
            finalColumns,
            orderColumn: [[ 3, "desc" ]],
            advanceFilter: () => {
                return {
                    order_date: $('#order_date').val(),
                    payment_status: $('#payment_status').val(),
                }
            }
        });

        $('#datatable').on('xhr.dt', function(e, settings, json, xhr) {
            if (json && json.data) {
                updateOrderReportAnalytics(json.data);
            }
        });
    })

    $('#order_date').on('change', function() {
        window.renderedDataTable.ajax.reload(null, false)
    })

    $('#payment_status').on('change', function() {
        window.renderedDataTable.ajax.reload(null, false)
    })
</script>
@endpush
