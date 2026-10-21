@extends('backend.layouts.app')

@section('title') {{ __($module_title) }} @endsection

@push('after-styles')
<link rel="stylesheet" href='{{ mix("modules/product/style.css") }}'>
<link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.css" />
<style>
    #datatable th,
    #datatable td {
        text-align: center;
    }
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
                    <i class="fa-solid fa-spa"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي الحجوزات والخدمات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-fin-bookings">0.00 ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">مبيعات المنتجات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-fin-orders">0.00 ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">بطاقات الإهداء</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-fin-giftcards">0.00 ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="report-summary-card" style="border-inline-start: 4px solid #4f46e5;">
                <div class="report-summary-icon bg-indigo bg-opacity-10 text-indigo" style="color:#4f46e5;">
                    <i class="fa-solid fa-vault"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">الإيراد المالي الإجمالي</div>
                    <div class="fs-4 fw-bolder text-primary" id="stat-fin-grand">0.00 ر.س</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- INTERACTIVE FINANCIAL MIX CHART                             --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="report-chart-container">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-chart-area text-primary me-2"></i> تطور مصادر الإيرادات المالية</h5>
                <p class="text-muted small mb-0">توزيع الدخل المالي بين جلسات السبا، مبيعات المنتجات، وبطاقات الإهداء</p>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold">
                تحليل مالي مباشر
            </span>
        </div>
        <div id="chart-financial-mix" style="min-height: 280px;"></div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- MAIN TABLE & FILTER                                         --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="card mb-3">
        <div class="card-body">
            <x-backend.section-header>
                <div class="row w-100">
                    <div class="col-md-3">
                        <div class="datatable-filter">
                            <select name="report_type" id="report_type" class="select2 form-control" data-filter="select">
                                <option value="daily">{{ __('report.lbl_daily') }}</option>
                                <option value="monthly">{{ __('report.lbl_monthly') }}</option>
                                <option value="custom">{{ __('report.lbl_custom_range') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="input-group gap-2 flex-nowrap">
                            <input type="text" name="date_range" id="date_range" placeholder="اختر التاريخ أو الفترة" class="financial-report-date-range form-control" readonly />
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
    let finMixChart = null;

    function updateFinancialReportAnalytics(rows) {
        let sumBookings = 0;
        let sumOrders = 0;
        let sumGiftcards = 0;
        let sumGrand = 0;

        let periods = [];
        let seriesBookings = [];
        let seriesOrders = [];
        let seriesGifts = [];

        if (rows && rows.length > 0) {
            const sortedRows = [...rows].reverse();

            sortedRows.forEach(row => {
                const bAmt = Number(row.raw_bookings_total ?? (parseFloat((row.bookings_total || '0').replace(/[^0-9.-]+/g, '')) || 0));
                const oAmt = Number(row.raw_orders_total ?? (parseFloat((row.orders_total || '0').replace(/[^0-9.-]+/g, '')) || 0));
                const gAmt = Number(row.raw_giftcards_total ?? (parseFloat((row.giftcards_total || '0').replace(/[^0-9.-]+/g, '')) || 0));
                const totalAmt = Number(row.raw_grand_total ?? (parseFloat((row.grand_total || '0').replace(/[^0-9.-]+/g, '')) || 0));

                sumBookings += bAmt;
                sumOrders += oAmt;
                sumGiftcards += gAmt;
                sumGrand += totalAmt;

                periods.push(row.period || '-');
                seriesBookings.push(+(Math.round(bAmt + "e+2") + "e-2"));
                seriesOrders.push(+(Math.round(oAmt + "e+2") + "e-2"));
                seriesGifts.push(+(Math.round(gAmt + "e+2") + "e-2"));
            });
        }

        const currencySymbol = ' ر.س';
        document.getElementById('stat-fin-bookings').innerText = sumBookings.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;
        document.getElementById('stat-fin-orders').innerText = sumOrders.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;
        document.getElementById('stat-fin-giftcards').innerText = sumGiftcards.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;
        document.getElementById('stat-fin-grand').innerText = sumGrand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;

        renderFinMixChart(periods, seriesBookings, seriesOrders, seriesGifts);
    }

    function renderFinMixChart(periods, bookings, orders, gifts) {
        const chartEl = document.querySelector('#chart-financial-mix');
        if (!chartEl || typeof ApexCharts === 'undefined') return;

        const chartOptions = {
            series: [
                { name: 'خدمات وحجوزات', data: bookings },
                { name: 'منتجات المتجر', data: orders },
                { name: 'بطاقات الإهداء', data: gifts }
            ],
            chart: {
                type: 'bar',
                height: 290,
                stacked: true,
                toolbar: { show: true, tools: { download: true, zoom: false, pan: false } },
                fontFamily: 'inherit'
            },
            colors: ['#4f46e5', '#059669', '#d97706'],
            plotOptions: { bar: { horizontal: false, columnWidth: '45%', borderRadius: 4 } },
            xaxis: { categories: periods, labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: v => Number(v || 0).toLocaleString() + ' ر.س' } },
            legend: { position: 'top' },
            dataLabels: { enabled: false },
            grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
        };

        if (finMixChart) {
            finMixChart.updateOptions(chartOptions);
        } else {
            finMixChart = new ApexCharts(chartEl, chartOptions);
            finMixChart.render();
        }
    }

    const range_flatpicker = document.querySelectorAll('.financial-report-date-range')

    Array.from(range_flatpicker, (elem) => {
        if (typeof flatpickr !== typeof undefined) {
            flatpickr(elem, {
                mode: "range",
                dateFormat: "d-m-Y",
            })
        }
    })

    $('#reset').on('click', function(e) {
        $('#date_range').val('');
        $('#report_type').val('daily').trigger('change');
        window.renderedDataTable.ajax.reload(null, false);
        $('.tooltip').removeClass('show');
    });

    const columns = [
        {
            data: 'period',
            name: 'period',
            title: "{{ __('report.lbl_period') }}",
            orderable: false,
            searchable: false,
            className: 'text-center',
        },
        {
            data: 'bookings_total',
            name: 'bookings_total',
            title: "{{ __('report.lbl_bookings_total') }}",
            orderable: false,
            searchable: false,
            className: 'text-center',
        },
        {
            data: 'orders_total',
            name: 'orders_total',
            title: "{{ __('report.lbl_orders_total') }}",
            orderable: false,
            searchable: false,
            className: 'text-center',
        },
        {
            data: 'giftcards_total',
            name: 'giftcards_total',
            title: "{{ __('report.lbl_giftcards_total') }}",
            orderable: false,
            searchable: false,
            className: 'text-center',
        },
        {
            data: 'grand_total',
            name: 'grand_total',
            title: "{{ __('report.lbl_grand_total') }}",
            orderable: false,
            searchable: false,
            className: 'text-center fw-bold text-success',
        },
    ]

    let finalColumns = [ ...columns ]

    document.addEventListener('DOMContentLoaded', (event) => {
        initDatatable({
            url: "{{ route('backend.reports.financial-report.index_data') }}",
            finalColumns,
            advanceFilter: () => {
                return {
                    report_type: $('[name="report_type"]').val(),
                    date_range: $('[name="date_range"]').val().split(' to '),
                }
            }
        });

        $('#datatable').on('xhr.dt', function(e, settings, json, xhr) {
            if (json && json.data) {
                updateFinancialReportAnalytics(json.data);
            }
        });
    })

    $(document).on('change', '#date_range', function() {
        window.renderedDataTable.ajax.reload(null, false)
    })

    $(document).on('change', '#report_type', function() {
        window.renderedDataTable.ajax.reload(null, false)
    })
</script>
@endpush
