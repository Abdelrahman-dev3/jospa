<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .deta:hover {color: white;}
</style>
<div class="position-relative overflow-hidden shadow" style="height: 300px;width: 100%;">
    <img src="{{ $image ?? asset('images/pages/main-bg.png') }}" alt="{{ $name ?? 'Package' }}" class="w-100 h-100" style="object-fit: cover;">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(to top, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0.0) 100%);"></div>
    <!-- Package info -->
    <div class="position-absolute bottom-0 start-0 w-100 p-3" style="height: 59%;">
      <div class="text-white h1 fw-bold" style="color: #BF9456 !important;white-space: nowrap;margin-bottom: 16px;">{{ $name ?? 'Package Name' }}</div>
      <p class="text-white h6" style="font-size: 18px;font-weight: 300;">{{ $description ?? 'Package Description' }}</p>
      <div class="d-flex justify-content-between align-items-center mt-2" style="font-size: 16px;">
          <span class="stars">
              <i class="fa-solid fa-star star"></i>
              <i class="fa-solid fa-star star"></i>
              <i class="fa-solid fa-star star"></i>
              <i class="fa-solid fa-star star"></i>
              <i class="fa-solid fa-star star"></i>
          </span>

          <a class="deta" href="{{ route('home.details', $package_id) }}" style="background: #BF9456;padding: 6px;width: 27%;text-align: center;border-radius: 17px;color: white;"> {{ __('messagess.details') }}</a>
      </div>

    </div>
</div>