<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<section class="py-5">
    <div class="container" style="padding: 0 5rem;">
        <h2 class="mb-5 text-center" style="color: var(--primary-color); font-size: 2.5rem; font-weight: bold;">
            {{ __('messages.our_premium_packages') }}
        </h2>

        @if(isset($packages) && $packages->count() > 0)
            <div class="row g-4">
                @foreach($packages as $index => $package)
                    <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                        @include('components.frontend.package-card', [
                            'image' => $package->feature_image,
                            'name' => $package->name,
                            'description' => Str::limit($package->description ?? '', 100),
                            'price' => 'SR ' . number_format($package->package_price ?? 0, 2),
                            'duration' => ($package->duration_min ?? 0) . ' ' . __('messages.min'),
                            'services_count' => $package->service ? $package->service->count() : 0,
                            'package_id' => $package->id
                        ])
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <p class="text-muted">{{ __('messages.no_packages_available') }}</p>
            </div>
        @endif
    </div>
</section>

<!-- Package Modal -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-labelledby="packageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="packageModalLabel">{{ __('messages.package_details') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.close') }}"></button>
      </div>
      <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
        <div id="packageDetailsContent">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">{{ __('messages.loading') }}</span>
                </div>
                <p class="mt-2">{{ __('messages.loading_package_details') }}</p>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ once: true });

  // Function to show package details
  function showPackageDetails(packageId) {
    const modal = new bootstrap.Modal(document.getElementById('packageModal'));
    const contentDiv = document.getElementById('packageDetailsContent');

    contentDiv.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">{{ __('messages.loading') }}</span>
        </div>
        <p class="mt-2">{{ __('messages.loading_package_details') }}</p>
      </div>
    `;

    const scrollPos = window.pageYOffset || document.documentElement.scrollTop;
    modal.show();
    setTimeout(() => { window.scrollTo(0, scrollPos); }, 100);

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
                <h6>{{ __('messages.included_services') }}:</h6>
                <ul class="list-unstyled">
                  ${package.service.map(ps => `
                    <li class="mb-2">
                      <i class="fas fa-check text-success me-2"></i>
                      ${ps.services ? ps.services.name : '{{ __('messages.service') }}'}
                      ${ps.services ? `(${ps.services.duration_min} {{ __('messages.min') }})` : ''}
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
                <h4 class="text-primary mb-3">${package.name}</h4>
                <p class="text-muted mb-3">${package.description || '{{ __('messages.no_description_available') }}'}</p>
                <div class="row mb-3">
                  <div class="col-6">
                    <strong>{{ __('messages.price') }}:</strong><br>
                    <span class="text-primary h5">SR ${parseFloat(package.package_price || 0).toFixed(2)}</span>
                  </div>
                  <div class="col-6">
                    <strong>{{ __('messages.services_included') }}:</strong><br>
                    <span class="badge bg-secondary">${package.service ? package.service.length : 0} {{ __('messages.services') }}</span>
                  </div>
                </div>
                ${servicesHtml}
              </div>
            </div>
          `;
        } else {
          contentDiv.innerHTML = `
            <div class="text-center text-danger">
              <p>{{ __('messages.error_loading_package_details') }}</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        contentDiv.innerHTML = `
          <div class="text-center text-danger">
            <p>{{ __('messages.error_loading_package_details') }}</p>
          </div>
        `;
      });
  }

  // Fix modal backdrop issue
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
