@extends('backend.layouts.app')

@section('title')
{{ __('reviews.title') }} #{{ $review->id }}
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <a href="{{ route('backend.booking-reviews.index') }}" class="btn btn-soft-dark mb-3">&larr; {{ __('reviews.title') }}</a>
        <div class="row">
            <div class="col-md-6">
                <table class="table">
                    <tr><th>{{ __('reviews.customer') }}</th><td>{{ $review->user->full_name ?? '-' }} ({{ $review->user->mobile ?? '' }})</td></tr>
                    <tr><th>{{ __('reviews.booking') }}</th><td>#{{ $review->booking_id }}</td></tr>
                    <tr><th>{{ __('reviews.service') }}</th><td>{{ $review->service->name ?? '-' }}</td></tr>
                    <tr><th>{{ __('reviews.specialist') }}</th><td>{{ $review->employee->full_name ?? '-' }}</td></tr>
                    <tr><th>{{ __('reviews.branch') }}</th><td>{{ $review->branch->name ?? '-' }}</td></tr>
                    <tr><th>{{ __('reviews.source') }}</th><td>{{ $review->source === 'whatsapp' ? __('reviews.source_whatsapp') : __('reviews.source_website') }}</td></tr>
                    <tr><th>{{ __('reviews.date') }}</th><td>{{ optional($review->completed_at)->format('Y-m-d H:i') }}</td></tr>
                    <tr><th>{{ __('reviews.points_given') }}</th><td>{{ $review->points_awarded }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                @foreach ($questions as $step => $question)
                    <div class="mb-3">
                        <div class="text-muted small">{{ $step }}. {{ $question['text'] }}</div>
                        <strong>{{ $question['options'][$review->{$question['field']}] ?? '-' }}</strong>
                    </div>
                @endforeach
                <div class="text-muted">{{ __('reviews.average') }}: <strong>{{ $review->average_rating ?? '-' }} / 4</strong></div>
            </div>
        </div>
    </div>
</div>
@endsection
