<style>
#carouselExampleSlidesOnly,
#carouselExampleSlidesOnly .carousel-inner,
#carouselExampleSlidesOnly .carousel-item,
#carouselExampleSlidesOnly img {
    height: 100vh;
}
#carouselExampleSlidesOnly img {
    object-fit: cover;
}
</style>


<div id="carouselExampleSlidesOnly" class="carousel slide carousel-fade position-relative" data-bs-ride="carousel">
    <div class="position-absolute top-0 start-0 w-100 bg-black" style="opacity: 0.4; height: 100%; z-index: 5"></div>
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="{{ asset('images/frontend/slider1.webp') }}" class="d-block w-100" alt="...">
        </div>
        <div class="carousel-item">
            <img src="{{ asset('images/frontend/slider2.webp') }}" class="d-block w-100" alt="...">
        </div>
    </div>

    {{ $slot }}
</div>
