@extends('backend.layouts.app')

@section('title')
{{ __('reviews.title') }}
@endsection

@push('after-styles')
<style>
    .rv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .rv-stat { background: #fff; border: 1px solid #e7ecf2; border-radius: 16px; padding: 14px 16px; }
    .rv-stat small { color: #5f6b7a; display: block; margin-bottom: 4px; }
    .rv-stat strong { font-size: 22px; }
    .rv-card { background: #fff; border: 1px solid #e7ecf2; border-radius: 18px; padding: 16px; margin-bottom: 18px; }
    .rv-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #fff; }
    .rv-badge.website { background: #2f6fed; }
    .rv-badge.whatsapp { background: #149167; }
    .rv-bar { background: #eef1f5; border-radius: 8px; height: 10px; overflow: hidden; }
    .rv-bar span { display: block; height: 100%; background: #bf9456; }
</style>
@endpush

@section('content')
@php
    $scaleLabel = fn ($v) => $v ? number_format($v, 2) . ' / 4' : '-';
@endphp
<div class="rv-grid">
    <div class="rv-stat"><small>{{ __('reviews.total') }}</small><strong>{{ $stats['total'] }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.avg_overall') }}</small><strong>{{ $scaleLabel($stats['avg_overall']) }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.avg_specialist') }}</small><strong>{{ $scaleLabel($stats['avg_specialist']) }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.avg_service') }}</small><strong>{{ $scaleLabel($stats['avg_service']) }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.source_website') }}</small><strong>{{ $stats['website'] }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.source_whatsapp') }}</small><strong>{{ $stats['whatsapp'] }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.points_given') }}</small><strong>{{ $stats['points'] }}</strong></div>
    <div class="rv-stat"><small>{{ __('reviews.awaiting') }}</small><strong>{{ $stats['awaiting'] }}</strong></div>
</div>

<form method="GET" class="rv-card">
    <div class="row g-2">
        <div class="col-md-2">
            <select name="source" class="form-select">
                <option value="">{{ __('reviews.all_sources') }}</option>
                <option value="website" @selected(request('source') === 'website')>{{ __('reviews.source_website') }}</option>
                <option value="whatsapp" @selected(request('source') === 'whatsapp')>{{ __('reviews.source_whatsapp') }}</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="rating" class="form-select">
                <option value="">{{ __('reviews.all_ratings') }}</option>
                @foreach ($questions[1]['options'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) request('rating') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="branch_id" class="form-select">
                <option value="">{{ __('reviews.all_branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="employee_id" class="form-select">
                <option value="">{{ __('reviews.all_specialists') }}</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-md-4"><input type="text" name="customer" value="{{ request('customer') }}" class="form-control" placeholder="{{ __('reviews.search_customer') }}"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary">{{ __('reviews.filter') }}</button></div>
        <div class="col-md-2 d-grid"><a href="{{ route('backend.booking-reviews.index') }}" class="btn btn-soft-dark">{{ __('reviews.reset') }}</a></div>
    </div>
</form>

<div class="row">
    <div class="col-lg-4">
        <div class="rv-card">
            <h6>{{ __('reviews.overall_distribution') }}</h6>
            @foreach ($questions[1]['options'] as $value => $label)
                @php $count = $distribution[$value] ?? 0; $pct = $stats['total'] ? round($count / $stats['total'] * 100) : 0; @endphp
                <div class="d-flex justify-content-between small mt-2"><span>{{ $label }}</span><span>{{ $count }} ({{ $pct }}%)</span></div>
                <div class="rv-bar"><span style="width: {{ $pct }}%"></span></div>
            @endforeach
        </div>
        <div class="rv-card">
            <h6>{{ __('reviews.by_specialist') }}</h6>
            @forelse ($byEmployee as $row)
                <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span>{{ $employeeNames[$row->employee_id]->full_name ?? '#' . $row->employee_id }} <span class="text-muted">({{ $row->total }})</span></span>
                    <strong>{{ number_format($row->avg_specialist, 2) }}</strong>
                </div>
            @empty
                <span class="text-muted small">-</span>
            @endforelse
        </div>
        <div class="rv-card">
            <h6>{{ __('reviews.by_branch') }}</h6>
            @forelse ($byBranch as $row)
                <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span>{{ $branchNames[$row->branch_id]->name ?? '#' . $row->branch_id }} <span class="text-muted">({{ $row->total }})</span></span>
                    <strong>{{ number_format($row->avg_overall, 2) }}</strong>
                </div>
            @empty
                <span class="text-muted small">-</span>
            @endforelse
        </div>
    </div>
    <div class="col-lg-8">
        <div class="rv-card p-0 table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('reviews.customer') }}</th>
                        <th>{{ __('reviews.booking') }}</th>
                        <th>{{ __('reviews.specialist') }}</th>
                        <th>{{ __('reviews.branch') }}</th>
                        <th>{{ __('reviews.ratings') }}</th>
                        <th>{{ __('reviews.source') }}</th>
                        <th>{{ __('reviews.date') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($reviews as $review)
                    <tr>
                        <td>{{ $review->id }}</td>
                        <td>{{ $review->user->full_name ?? '-' }}<br><small class="text-muted">{{ $review->user->mobile ?? '' }}</small></td>
                        <td>#{{ $review->booking_id }}<br><small class="text-muted">{{ $review->service->name ?? '' }}</small></td>
                        <td>{{ $review->employee->full_name ?? '-' }}</td>
                        <td>{{ $review->branch->name ?? '-' }}</td>
                        <td class="small">
                            {{ $questions[1]['options'][$review->overall_rating] ?? '-' }}<br>
                            {{ $questions[2]['options'][$review->specialist_rating] ?? '-' }}<br>
                            {{ $questions[3]['options'][$review->service_rating] ?? '-' }}
                        </td>
                        <td><span class="rv-badge {{ $review->source }}">{{ $review->source === 'whatsapp' ? __('reviews.source_whatsapp') : __('reviews.source_website') }}</span></td>
                        <td class="small">{{ optional($review->completed_at)->format('Y-m-d H:i') }}</td>
                        <td><a class="btn btn-sm btn-soft-primary" href="{{ route('backend.booking-reviews.show', $review->id) }}"><i class="fa-solid fa-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">{{ __('reviews.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $reviews->links() }}
    </div>
</div>
@endsection
