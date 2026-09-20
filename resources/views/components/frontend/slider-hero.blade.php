<!-- backend -->
@php
    use App\Models\Ad;
    use App\Models\Vartext;

    $lang = app()->getLocale();
    $nationalDayTheme = (int) setting('saudi_national_day_theme', 0) === 1;
    $vartext = Vartext::where('type','banner')->first();
    $ads = Ad::where('page', 'home')->where('status', 1)->get();
@endphp

<!-- css -->
<link rel="stylesheet" href="{{ asset('pages-css/slider-hero.css') }}">


<!-- html -->
<div class="screen-hero {{ $nationalDayTheme ? 'screen-hero--national-day' : '' }}">
    @unless($nationalDayTheme)
        <img src="{{asset('Vector.png')}}" class="fl-1" alt="fl img" loading="lazy">
        <img src="{{asset('images/icons/fl-2.png')}}" class="fl-2" alt="fl img" loading="lazy" >
    @endunless
    
    
    <div class="hero-container">
        
        <div class="first-sec">
            @if($nationalDayTheme)
                <div class="national-day-badge" role="img" aria-label="{{ $lang === 'ar' ? 'اليوم الوطني السعودي ٩٦' : 'Saudi National Day 96' }}">
                    <svg class="national-day-emblem" viewBox="0 0 80 72" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M40 43V18 M40 24Q27 9 16 24 M40 24Q25 15 20 32 M40 24Q31 9 28 17 M40 24Q40 5 40 11 M40 24Q53 9 64 24 M40 24Q55 15 60 32 M40 24Q49 9 52 17 M40 30l-3 4 3 4-3 4"/>
                        <path d="M15 48Q39 68 65 45 M65 48Q41 68 15 45 M17 45l-5 8 M63 45l5 8 M12 51l-5-3 M68 51l5-3"/>
                    </svg>
                    <span class="national-day-badge-label">{{ $lang === 'ar' ? 'اليوم الوطني السعودي ٩٦' : 'Saudi National Day 96' }}</span>
                </div>
            @endif
            <h1>{{ $vartext->title[$lang] ?? $vartext->title['en'] ?? '' }}</h1>
            <p >{{ $vartext->description[$lang] ?? $vartext->description['en'] ?? '' }}</p>

            <div class="buttons">
                <a href="#bookNaw" class="gradient-border btn-primary">
                    <span class="iconify" data-icon="uil:calender" data-width="32"></span>
                    {{ __('messagess.book_now') }}
                </a>

                <a href="{{ route('gift.page') }}" class="gradient-border btn-outline-light">
                    <span class="iconify" data-icon="basil:present-outline" data-width="32"></span>
                    {{ __('messagess.choose_your_gift') }}
                </a>
            </div>
        </div>

        <div class="second-sec">
            @if($ads->count())
                <div class="slider">
                    @foreach($ads as $key => $item)
                        @if($item->link)
                            <a href="{{ $item->link }}" class="slide-link {{ $key === 0 ? 'active' : '' }}" target="_blank">
                                <img src="{{ asset($item->image) }}" alt="Slide">
                            </a>
                        @else
                            <div class="slide-link {{ $key === 0 ? 'active' : '' }}">
                                <img src="{{ asset($item->image) }}" alt="Slide">
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="pagination">
                    @foreach($ads as $key => $item)
                        <span class="{{ $key === 0 ? 'active' : '' }}"></span>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>

<!-- script -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>
<script>
$(document).ready(function () {

    let currentIndex = 0;
    const slides = $('.slider .slide-link');
    const dots = $('.pagination span');
    const slideCount = slides.length;

    function showSlide(index) {
        slides.removeClass('active').eq(index).addClass('active');
        dots.removeClass('active').eq(index).addClass('active');
    }

    function nextSlide() {
        currentIndex = (currentIndex + 1) % slideCount;
        showSlide(currentIndex);
    }

    let interval = setInterval(nextSlide, 3000);

    dots.on('click', function () {
        currentIndex = $(this).index();
        showSlide(currentIndex);
        clearInterval(interval);
        interval = setInterval(nextSlide, 3000);
    });

});
</script>
