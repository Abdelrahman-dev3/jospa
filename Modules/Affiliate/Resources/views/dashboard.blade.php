@extends('affiliate::layouts.master')

@section('title', 'لوحة التحكم')

@section('content')

<div class="row">

    {{-- Visitors --}}
    <div class="col-lg-3 col-md-6">
        <div class="card card-block card-stretch card-height">
            <div class="card-body">
                <h6 class="text-muted">الزوار</h6>
                <h3 class="mb-0">{{ $totalVisitors }}</h3>
            </div>
        </div>
    </div>

    {{-- Conversions --}}
    <div class="col-lg-3 col-md-6">
        <div class="card card-block card-stretch card-height">
            <div class="card-body">
                <h6 class="text-muted">التحويلات</h6>
                <h3 class="mb-0">{{ $totalConversions }}</h3>
            </div>
        </div>
    </div>

    {{-- Total Earnings --}}
    <div class="col-lg-3 col-md-6">
        <div class="card card-block card-stretch card-height">
            <div class="card-body">
                <h6 class="text-muted">إجمالي الأرباح</h6>
                <h3 class="mb-0">$ {{ number_format($totalEarnings, 2) }}</h3>
            </div>
        </div>
    </div>

    {{-- Available Balance --}}
    <div class="col-lg-3 col-md-6">
        <div class="card card-block card-stretch card-height">
            <div class="card-body">
                <h6 class="text-muted">الرصيد المتاح</h6>
                <h3 class="mb-0 text-success">$ {{ number_format($availableEarnings, 2) }}</h3>
            </div>
        </div>
    </div>
</div>


{{-- ===== CHART ===== --}}
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">الزوار آخر 30 يوم</h5>
    </div>
    <div class="card-body">
        <canvas id="visitorsChart" height="100"></canvas>
    </div>
</div>


{{-- ===== TOP LINKS ===== --}}
<div class="row mt-4">
    {{-- <div class="col-lg-6">
        <div class="card card-block">
            <div class="card-header">
                <h5>أفضل الروابط</h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    @forelse ($topLinks as $item)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $item->ref_url }}</span>
                            <strong>{{ $item->total }}</strong>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">لا يوجد بيانات</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div> --}}

    {{-- LAST CONVERSIONS --}}
    <div class="col-lg-6">
        <div class="card card-block">
            <div class="card-header">
                <h5>آخر التحويلات</h5>
            </div>

            <div class="card-body">
                <ul class="list-group">
                    @forelse ($lastConversions as $conv)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>طلب #{{ $conv->order_id }}</span>
                            <span class="text-success">${{ $conv->commission }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">لا يوجد تحويلات</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let ctx = document.getElementById('visitorsChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                label: 'Visitors',
                data: @json($chartData),
                borderWidth: 2,
                borderColor: "#4e73df",
                fill: false,
                tension: 0.3
            }]
        }
    });
</script>
@endpush
