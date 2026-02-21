<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<section class="py-5" style="position: relative;">
<style>

</style>
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

  function __(key) {
    const messagess = {
      'messagess.package_details': @json(__('messagess.package_details')),
      'messagess.loading': @json(__('messagess.loading')),
      'messagess.included_services': @json(__('messagess.included_services')),
      'messagess.service': @json(__('messagess.service')),
      'messagess.price': @json(__('messagess.price')),
      'messagess.services_included': @json(__('messagess.services_included')),
      'messagess.services_count': @json(__('messagess.services_count')),
      'messagess.error_loading': @json(__('messagess.error_loading')),
    };
    return messagess[key] || key;
  }

  function showPackageDetails(packageId) {
    const modal = new bootstrap.Modal(document.getElementById('packageModal'));
    const contentDiv = document.getElementById('packageDetailsContent');

    contentDiv.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">${__('messagess.loading')}</p>
      </div>
    `;

    const scrollPos = window.pageYOffset || document.documentElement.scrollTop;
    modal.show();
    setTimeout(() => {
      window.scrollTo(0, scrollPos);
    }, 100);

    fetch(`/api/v1/packages/${packageId}`)
      .then(response => response.json())
      .then(data => {
        if (data.status && data.data) {
          const package = data.data;
          let servicesHtml = '';
          const packageImage = package.media && package.media.length > 0 ?
            package.media[0].original_url :
            '{{ asset("images/frontend/slider1.webp") }}';

          if (package.service && package.service.length > 0) {
            servicesHtml = `
              <div class="mt-3">
                <h6>${__('messagess.included_services')}</h6>
                <ul class="list-unstyled">
                  ${package.service.map(ps => `
                    <li class="mb-2">
                      <i class="fas fa-check text-success me-2"></i>
                      ${ps.services ? ps.services.name["{{ app()->getLocale() }}"] : __('messagess.service')}
                      ${ps.services ? `(${ps.services.duration_min} min)` : ''}
                    </li>
                  `).join('')}
                </ul>
              </div>
            `;
          }

          contentDiv.innerHTML = `
            <div class="row">
              <div class="col-md-6">
                <img src="${packageImage}" alt="${package.name}" class="img-fluid rounded" style="max-height: 300px; object-fit: cover;">
              </div>
              <div class="col-md-6">
                <h4 class="text-primary mb-3">${package.name["{{ app()->getLocale() }}"]}</h4>
                <p class="text-muted mb-3">${package.description || __('messagess.no_packages_available')}</p>
                <div class="row mb-3">
                  <div class="col-6">
                    <strong>${__('messagess.price')}</strong><br>
                    <span class="text-primary h5">SR ${parseFloat(package.package_price || 0).toFixed(2)}</span>
                  </div>
                  <div class="col-6">
                    <strong>${__('messagess.services_included')}</strong><br>
                    <span class="badge bg-secondary">${package.service ? package.service.length : 0} </span>
                  </div>
                </div>
                ${servicesHtml}
              </div>
            </div>
          `;
        } else {
          contentDiv.innerHTML = `
            <div class="text-center text-danger">
              <p>${__('messagess.error_loading')}</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        contentDiv.innerHTML = `
          <div class="text-center text-danger">
            <p>${__('messagess.error_loading')}</p>
          </div>
        `;
      });
  }

  document.addEventListener('DOMContentLoaded', function() {
    const packageModal = document.getElementById('packageModal');

    if (packageModal) {
      packageModal.addEventListener('hidden.bs.modal', function () {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
        document.body.style.overflow = '';
      });
    }
  });
</script>
