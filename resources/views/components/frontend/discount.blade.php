@php
    use App\Models\Ouroffersection;
    use Carbon\Carbon;
    $page = Ouroffersection::where('end_date', '>', Carbon::now())->latest()->first();
@endphp
<link rel="stylesheet" href="{{ asset('pages-css/discount.css') }}">
@if($page)
<div class="head-discount">
    <div class="radius">
        <div class="discount-section">
            @if($page)
                @php
                    $startDate = Carbon::parse($page->start_date ?? 0)->translatedFormat('l d-m-Y');
                    $endDate   = Carbon::parse($page->end_date ?? 0)->translatedFormat('l d-m-Y');
                    $description = $page->description[app()->getLocale()] ?? '';
                    $originalPrice = 500;
        
                    if ($page->discount_type === 'percentage') {
                        $result = $originalPrice - ($originalPrice * ($page->discount_value / 100));
                    } else {
                        $result = $originalPrice - $page->discount_value;
                    }
                @endphp
                <h1>{{ __('messages.discount') }} {{ intval($page->discount_value) }} {{ $page->discount_type == 'percentage' ? '%' : 'ريال' }}</h1>
                <h3>{{ $description }} "vdsfimvdfojbdgoindgoi nfgonfgon"</h3>
                <div style="width: 17%;margin: auto;">
                    <a href="{{route('frontend.Ouroffers')}}" class="more-btn-discount">
                        <p style="color:white;font-size: 16px;margin: 0 13px;color:#BF9456">{{ __('messagess.learn_more') }}</p>
                    </a>
                </div>
            @endif
        </div>
        <img src="{{ asset('fl-dis.png') }}" alt="" style="position: absolute;left: 0;bottom: -11px;width: 195px;">
        <img src="{{asset('wavy.png')}}" alt="fl img" style="position: absolute;right: 0;bottom: 0;">
    </div>
</div>
@endif