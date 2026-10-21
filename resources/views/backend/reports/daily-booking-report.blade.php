@extends('backend.layouts.app', ['isNoUISlider' => true])

@section('title')
    {{ $module_title }}
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ mix('modules/service/style.css') }}">
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
    {{-- SUMMARY KPI CARDS (Drives from Table Data in Real Time)     --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4 col-xl">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي الحجوزات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-total-bookings">0</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-spa"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي الخدمات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-total-services">0</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">مبالغ الخدمات</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-service-amount">0.00 ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-6 col-xl">
            <div class="report-summary-card">
                <div class="report-summary-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">إجمالي الضرائب</div>
                    <div class="fs-4 fw-bolder text-dark" id="stat-tax-amount">0.00 ر.س</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl">
            <div class="report-summary-card" style="border-inline-start: 4px solid #059669;">
                <div class="report-summary-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">المبلغ الإجمالي النهائي</div>
                    <div class="fs-4 fw-bolder text-success" id="stat-final-amount">0.00 ر.س</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- INTERACTIVE REPORT CHART                                    --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="report-chart-container">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i> مسار الحجوزات والإيرادات اليومية</h5>
                <p class="text-muted small mb-0">تحليل بياني لحجم الحجوزات اليومية وإجمالي المبالغ المحصلة</p>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold">
                تحديث تلقائي مع الفلترة
            </span>
        </div>
        <div id="chart-daily-bookings" style="min-height: 280px;"></div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- MAIN REPORT DATATABLE                                       --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-body">
            <x-backend.section-header>
              <div>
                <button type="button" class="btn btn-secondary" data-modal="export">
                  <i class="fa-solid fa-download"></i> {{ __('messages.export') }}
                </button>
              </div>
                <x-slot name="toolbar">
                    <div class="input-group gap-2 flex-nowrap">
                        <input type="text" name="booking_date" id="booking_date" placeholder="اختر التاريخ أو الفترة" class="booking-date-range form-control" readonly />
                        <button id="reset" class="btn bg-primary rounded" data-bs-toggle="tooltip" title="إعادة تعيين">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21.4799 12.2424C21.7557 12.2326 21.9886 12.4482 21.9852 12.7241C21.9595 14.8075 21.2975 16.8392 20.0799 18.5506C18.7652 20.3986 16.8748 21.7718 14.6964 22.4612C12.518 23.1505 10.1711 23.1183 8.01299 22.3694C5.85488 21.6205 4.00382 20.196 2.74167 18.3126C1.47952 16.4293 0.875433 14.1905 1.02139 11.937C1.16734 9.68346 2.05534 7.53876 3.55018 5.82945C5.04501 4.12014 7.06478 2.93987 9.30193 2.46835C11.5391 1.99683 13.8711 2.2599 15.9428 3.2175L16.7558 1.91838C16.9822 1.55679 17.5282 1.62643 17.6565 2.03324L18.8635 5.85986C18.945 6.11851 18.8055 6.39505 18.549 6.48314L14.6564 7.82007C14.2314 7.96603 13.8445 7.52091 14.0483 7.12042L14.6828 5.87345C13.1977 5.18699 11.526 4.9984 9.92231 5.33642C8.31859 5.67443 6.8707 6.52052 5.79911 7.74586C4.72753 8.97119 4.09095 10.5086 3.98633 12.1241C3.8817 13.7395 4.31474 15.3445 5.21953 16.6945C6.12431 18.0446 7.45126 19.0658 8.99832 19.6027C10.5454 20.1395 12.2278 20.1626 13.7894 19.6684C15.351 19.1743 16.7062 18.1899 17.6486 16.8652C18.4937 15.6773 18.9654 14.2742 19.0113 12.8307C19.0201 12.5545 19.2341 12.3223 19.5103 12.3125L21.4799 12.2424Z"
                                    fill="#ffffff"></path>
                            </svg>
                        </button>
                    </div>
                </x-slot>
            </x-backend.section-header>
            <table id="datatable" class="table table-striped border table-responsive">
            </table>
        </div>
    </div>
    <x-backend.advance-filter>
        <x-slot name="title">
            <h4>Advanced Filter</h4>
        </x-slot>
    </x-backend.advance-filter>
@endsection

@push('after-scripts')
    <script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script src="{{ asset('js/form-modal/index.js') }}" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.40.0/apexcharts.min.js"></script>

    <script type="text/javascript">
        let dailyReportChart = null;

        function updateDailyReportAnalytics(rows) {
            let totalBookings = 0;
            let totalServices = 0;
            let totalServiceAmt = 0;
            let totalTaxAmt = 0;
            let totalFinalAmt = 0;

            let chartDates = [];
            let chartBookings = [];
            let chartAmounts = [];

            if (rows && rows.length > 0) {
                // Sort ascending by date for chart timeline
                const sortedRows = [...rows].reverse();

                sortedRows.forEach(row => {
                    const bCount = Number(row.raw_total_booking ?? row.total_booking ?? 0);
                    const sCount = Number(row.raw_total_service ?? row.total_service ?? 0);
                    const sAmt   = Number(row.raw_service_amount ?? (parseFloat((row.total_service_amount || '0').replace(/[^0-9.-]+/g, '')) || 0));
                    const tAmt   = Number(row.raw_tax_amount ?? (parseFloat((row.total_tax_amount || '0').replace(/[^0-9.-]+/g, '')) || 0));
                    const fAmt   = Number(row.raw_total_amount ?? (parseFloat((row.total_amount || '0').replace(/[^0-9.-]+/g, '')) || 0));

                    totalBookings += bCount;
                    totalServices += sCount;
                    totalServiceAmt += sAmt;
                    totalTaxAmt += tAmt;
                    totalFinalAmt += fAmt;

                    chartDates.push(row.start_date_time || '-');
                    chartBookings.push(bCount);
                    chartAmounts.push(roundToTwo(fAmt));
                });
            }

            // Update KPI cards
            const currencySymbol = ' ر.س';
            document.getElementById('stat-total-bookings').innerText = totalBookings.toLocaleString();
            document.getElementById('stat-total-services').innerText = totalServices.toLocaleString();
            document.getElementById('stat-service-amount').innerText = totalServiceAmt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;
            document.getElementById('stat-tax-amount').innerText = totalTaxAmt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;
            document.getElementById('stat-final-amount').innerText = totalFinalAmt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + currencySymbol;

            // Render or Update ApexChart
            renderDailyChart(chartDates, chartBookings, chartAmounts);
        }

        function roundToTwo(num) {
            return +(Math.round(num + "e+2")  + "e-2");
        }

        function renderDailyChart(dates, bookings, amounts) {
            const chartEl = document.querySelector('#chart-daily-bookings');
            if (!chartEl || typeof ApexCharts === 'undefined') return;

            const chartOptions = {
                series: [
                    { name: 'الإجمالي النهائي (ر.س)', type: 'area', data: amounts },
                    { name: 'عدد الحجوزات', type: 'column', data: bookings }
                ],
                chart: {
                    height: 290,
                    type: 'line',
                    toolbar: { show: true, tools: { download: true, zoom: false, pan: false } },
                    fontFamily: 'inherit'
                },
                colors: ['#059669', '#4f46e5'],
                stroke: { width: [3, 0], curve: 'smooth' },
                fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                xaxis: { categories: dates, labels: { style: { fontSize: '11px' } } },
                yaxis: [
                    { title: { text: 'الإيراد (ر.س)' }, labels: { formatter: v => Number(v || 0).toLocaleString() } },
                    { opposite: true, title: { text: 'الحجوزات' }, labels: { formatter: v => Number(v || 0).toFixed(0) }, min: 0 }
                ],
                legend: { position: 'top' },
                grid: { strokeDashArray: 4, borderColor: '#f1f5f9' }
            };

            if (dailyReportChart) {
                dailyReportChart.updateOptions(chartOptions);
            } else {
                dailyReportChart = new ApexCharts(chartEl, chartOptions);
                dailyReportChart.render();
            }
        }

        const range_flatpicker = document.querySelectorAll('.booking-date-range')
        Array.from(range_flatpicker, (elem) => {
            if (typeof flatpickr !== typeof undefined) {
                flatpickr(elem, {
                    mode: "range",
                    dateFormat: "d-m-Y",
                })
            }
        })

        $('#reset').on('click', function(e) {
            $('#booking_date').val('');
            window.renderedDataTable.ajax.reload(null, false);
            $('.tooltip').removeClass('show');
        });

        const columns = [{
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                title: "{{ __('report.lbl_no') }}",
                orderable: false,
                searchable: false
            },
            {
                data: 'start_date_time',
                name: 'start_date_time',
                title: "{{ __('report.lbl_booking_date') }}",
            },
            {
                data: 'total_booking',
                name: 'total_booking',
                title: "{{ __('report.lbl_no_booking') }}",
                width: '10%'
            },
            {
                data: 'total_service',
                name: 'total_service',
                title: "{{ __('report.lbl_no_services') }}",
                width: '10%'
            },
            {
                data: 'total_service_amount',
                name: 'total_service_amount',
                title: "{{ __('report.lbl_service_amount') }}",
            },
            {
                data: 'total_tax_amount',
                name: 'total_tax_amount',
                title: "{{ __('report.lbl_tax_amt') }}",
            },
            {
                data: 'total_amount',
                name: 'total_amount',
                title: "{{ __('report.lbl_final_amt') }}",
            },
        ]

        let finalColumns = [ ...columns ];

        $('#booking_date').on('change', function() {
            window.renderedDataTable.ajax.reload(null, false)
        })

        document.addEventListener('DOMContentLoaded', (event) => {
            initDatatable({
                url: '{{ route('backend.reports.daily-booking-report.index_data') }}',
                finalColumns,
                orderColumn: [[ 1, "desc" ]],
                advanceFilter: () => {
                    return {
                        booking_date: $('#booking_date').val(),
                    }
                }
            });

            // Listen to DataTable response to update summaries and charts automatically!
            $('#datatable').on('xhr.dt', function(e, settings, json, xhr) {
                if (json && json.data) {
                    updateDailyReportAnalytics(json.data);
                }
            });
        })
    </script>
@endpush
