@php
    use App\Models\Branch;
    $branches = Branch::where('status', 1)->whereNull('deleted_by')->get();
@endphp
<link rel="stylesheet" href="{{ asset('pages-css/slider.css') }}">
    {{ $slot }}
<div class="main-head">
    <h2 class="mb-5 mt-3 text-center" style="position: relative;z-index: 1;font-size: 42px;color:#BF9456;font-weight: bold;">
        {{ __('messagess.our_branches') }}
    </h2>

        <div class="position-relative row justify-content-center g-4" style="margin-top: 60px;">
            @foreach($branches as $branch)

                <div class="col-12 col-md-3" style="display: flex;justify-content: center;">
                    <div class="branch-card">
                        <!-- صورة -->
                        <div class="branch-image">
                        <img src="{{ asset($branch->feature_image) }}" alt="{{ $branch->name }}">
                        </div>
                        <!-- المحتوى -->
                        <div class="branch-content">
                            <h3 class="branch-title">{{ $branch->name }}</h3>
                            <p class="branch-address">{{ $branch->description ?? '' }}</p>
                            <a href="{{ route('salon.create', ['branch_id' => $branch->id]) }}" class="more-btn-hero">
                              <p style="color:white;font-size: 16px;margin: 0 13px;">{{ __('messagess.book_now') }} <img style="width: 15px;" src="{{ asset('images/icons/Vector (2).png') }}" ></p>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
</div>


