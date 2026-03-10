<!-- backend -->
@php
    use App\Models\Ad;
    use App\Models\Vartext;

    $lang = app()->getLocale();
    $vartext = Vartext::where('type','banner')->first();
    $ads = Ad::where('page', 'home')->where('status', 1)->get();
@endphp

<!-- css -->
<link rel="stylesheet" href="{{ asset('pages-css/slider-hero.css') }}">


<!-- html -->
<div class="screen-hero">
    <img src="{{asset('Vector.png')}}" class="fl-1" alt="fl img" loading="lazy">
    <img src="{{asset('images/icons/fl-2.png')}}" class="fl-2" alt="fl img" loading="lazy" >
    
    
    <div class="hero-container">
        
        <div class="first-sec">
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
