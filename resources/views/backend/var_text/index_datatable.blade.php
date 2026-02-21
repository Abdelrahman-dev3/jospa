@extends('backend.layouts.app')

@section('title')
    {{ __('messages.main_web_texts') }}
@endsection

@section('content')

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="fa-solid fa-pen-to-square me-2"></i>
                {{ __('messages.main_web_texts') }}
            </h4>
        </div>
    </div>
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check me-1"></i>
            {{ __('messages.saved_successfully') }}
        </div>
    @endif

    <form action="{{route('app.Text')}}" method="POST">
        @csrf
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fa-solid fa-house me-2 text-primary"></i>
                    {{ __('messages.banner_section') }}
                </h6>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.banner_title') }} AR</label>
                    <input type="text" name="title_ar" class="form-control" value="{{ $banner->title['ar'] ?? '' }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.banner_title') }} EN</label>
                    <input type="text" name="title_en" class="form-control" value="{{ $banner->title['en'] ?? '' }}">
                </div>
                    <input type="hidden" name="type" class="form-control" value="banner">

                <div class="mb-3">
                    <label class="form-label">{{ __('messages.banner_subtitle') }} AR</label>
                    <textarea name="description_ar" class="form-control" rows="2">{{ $banner->description['ar'] ?? '' }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.banner_subtitle') }} EN</label>
                    <textarea name="description_en" class="form-control" rows="2">{{ $banner->description['en'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
        <div class="text-end">
            @hasPermission('store_vartext')
                <button class="btn btn-primary px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i>
                    {{ __('messages.save_changes') }}
                </button>
            @endhasPermission
        </div>
    </form>

    <form action="{{route('app.Text')}}" method="POST">
        @csrf
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fa-solid fa-gift me-2 text-success"></i>
                    {{ __('messages.gifts_section') }}
                </h6>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.gifts_title') }} AR</label>
                    <input type="text" name="title_ar" class="form-control" value="{{ $gift->title['ar'] ?? '' }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('messages.gifts_title') }} EN</label>
                    <input type="text" name="title_en" class="form-control" value="{{ $gift->title['en'] ?? '' }}">
                </div>

                    <input type="hidden" name="type" class="form-control" value="gift">

                <div class="mb-3">
                    <label class="form-label">{{ __('messages.gifts_description') }} AR</label>
                    <textarea name="description_ar" class="form-control" rows="3">{{ $gift->description['ar'] ?? '' }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.gifts_description') }} EN</label>
                    <textarea name="description_en" class="form-control" rows="3">{{ $gift->description['en'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
        <div class="text-end">
            <button class="btn btn-primary px-4">
                <i class="fa-solid fa-floppy-disk me-1"></i>
                {{ __('messages.save_changes') }}
            </button>
        </div>
    </form>

</div>

@endsection
