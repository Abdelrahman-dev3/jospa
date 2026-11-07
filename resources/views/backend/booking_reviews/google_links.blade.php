@extends('backend.layouts.app')

@section('title')
{{ __('reviews.google_links_title') }}
@endsection

@push('after-styles')
<style>
    .gl-card { background: #fff; border: 1px solid #e7ecf2; border-radius: 18px; padding: 20px; margin-bottom: 18px; }
    .gl-card h5 { margin-bottom: 4px; }
    .gl-card .text-muted { font-size: 13px; }
    .gl-row { display: grid; grid-template-columns: 200px 1fr auto; gap: 12px; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f2f5; }
    .gl-row:last-child { border-bottom: 0; }
    .gl-row input { direction: ltr; }
    @media (max-width: 768px) { .gl-row { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<a href="{{ route('backend.booking-reviews.index') }}" class="btn btn-soft-dark mb-3">&larr; {{ __('reviews.title') }}</a>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('backend.booking-reviews.google-links.save') }}">
    @csrf

    <div class="gl-card">
        <h5>{{ __('reviews.google_links_title') }}</h5>
        <p class="text-muted">{{ __('reviews.google_links_help') }}</p>

        @foreach ($branches as $branch)
            <div class="gl-row">
                <strong>{{ $branch->name }}</strong>
                <input type="url" name="links[{{ $branch->id }}]" class="form-control"
                       value="{{ old('links.' . $branch->id, $links[$branch->id] ?? '') }}"
                       placeholder="https://maps.app.goo.gl/...">
                @if (! empty($links[$branch->id]))
                    <a href="{{ $links[$branch->id] }}" target="_blank" rel="noopener" class="btn btn-sm btn-soft-primary">{{ __('reviews.google_links_test') }}</a>
                @else
                    <span class="badge bg-warning text-dark">{{ __('reviews.google_links_uses_default') }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="gl-card">
        <h5>{{ __('reviews.google_links_default') }}</h5>
        <p class="text-muted">{{ __('reviews.google_links_default_help') }}</p>
        <input type="url" name="default_link" class="form-control" style="direction: ltr;"
               value="{{ old('default_link', $defaultLink) }}" placeholder="https://maps.app.goo.gl/...">
    </div>

    <button class="btn btn-primary">{{ __('reviews.google_links_save') }}</button>
</form>
@endsection
