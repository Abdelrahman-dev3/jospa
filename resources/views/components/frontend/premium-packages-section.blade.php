<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<section class="py-5" style="position: relative;">
  <div class="container">
    <img src="{{asset('images/icons/fl-2.png')}}" alt="fl img" style="position: absolute;left: 0;top: 0;">
      <h2 class="mb-5 text-center" style="margin-bottom: 67px;margin-top: 140px;font-size: 50px;background: #BF9456;-webkit-background-clip: text;-webkit-text-fill-color: transparent; font-weight: bold;">
          {{ __('messagess.our_premium_packages') }}
      </h2>
      @if(isset($packages) && $packages->count() > 0)
          <div class="row gx-4 gy-4" style="--bs-gutter-x: 1rem !important;">
              @foreach($packages as $index => $package)
                  <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                      @include('components.frontend.package-card', [
                          'image' => $package->media->first()->original_url ?? asset('images/pages/main-bg.png'),
                          'name' => $package->name,
                          'description' => Str::limit($package->description ?? '', 100),
                          'price' => 'SR ' . number_format($package->package_price ?? 0, 2),
                          'duration' => $package->duration_min ?? 0 . ' min',
                          'services_count' => $package->service ? $package->service->count() : 0,
                          'package_id' => $package->id
                      ])
                  </div>
              @endforeach
          </div>
      @else
          <div class="text-center py-5">
              <p class="text-muted">{{ __('messagess.no_packages_available') }}</p>
          </div>
      @endif
  </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    AOS.init({ once: true });
  </script>