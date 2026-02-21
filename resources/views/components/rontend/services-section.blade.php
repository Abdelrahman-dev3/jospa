<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<section class="py-5">
    <div class="container" style="padding: 0 5rem;">
        <h2 class="mb-5 text-center" style="color: var(--primary-color); font-size: 2.5rem; font-weight: bold;">
            {{ __('messages.our_service_categories') }}
        </h2>

        @if(isset($categories) && $categories->count() > 0)
            <div class="row g-4">
                @foreach($categories as $index => $category)
                    <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                        @include('components.frontend.category-card', [
                            'image' => asset('images/frontend/slider' . (($index % 3) + 1) . '.webp'),
                            'name' => $category->name,
                            'description' => Str::limit($category->description ?? __('messages.our_service_categories') . ' ' . $category->name, 100),
                            'services_count' => $category->services ? $category->services->count() : 0,
                            'price_range' => $category->services && $category->services->count() > 0 && $category->services->whereNotNull('default_price')->count() > 0 ?
                                'SR ' . number_format($category->services->whereNotNull('default_price')->min('default_price'), 2) . ' - SR ' . number_format($category->services->whereNotNull('default_price')->max('default_price'), 2) :
                                __('messages.contact_for_pricing'),
                            'category_id' => $category->id
                        ])
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <p class="text-muted">{{ __('messages.no_service_categories') }}</p>
            </div>
        @endif
    </div>
</section>

<!-- Pricing Modal -->
<div class="modal fade" id="pricingModal" tabindex="-1" aria-labelledby="pricingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pricingModalLabel">{{ __('messages.category_services_pricing') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.close') }}"></button>
            </div>
            <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                <div id="pricingTable">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('messages.loading_services') }}</span>
                        </div>
                        <p class="mt-2">{{ __('messages.loading_services') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Service Details Modal -->
<div class="modal fade" id="serviceDetailsModal" tabindex="-1" aria-labelledby="serviceDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serviceDetailsModalLabel">{{ __('messages.service_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.close') }}"></button>
            </div>
            <div class="modal-body">
                <div id="serviceDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('messages.loading_service_details') }}</span>
                        </div>
                        <p class="mt-2">{{ __('messages.loading_service_details') }}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('messages.close') }}</button>
                <button type="button" class="btn btn-primary">{{ __('messages.book_this_service') }}</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true });

    function showCategoryServices(categoryId) {
        const modal = new bootstrap.Modal(document.getElementById('pricingModal'));
        const contentDiv = document.getElementById('pricingTable');

        contentDiv.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">{{ __('messages.loading_services') }}</span>
        </div>
        <p class="mt-2">{{ __('messages.loading_services') }}</p>
      </div>
    `;

        modal.show();

        fetch(`/api/v1/services?category_id=${categoryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status && data.data && data.data.length > 0) {
                    const services = data.data;
                    let tableRows = '';

                    services.forEach(service => {
                        tableRows += `
              <tr>
                <td>${service.name}</td>
                <td>${service.category ? service.category.name : '{{ __('messages.general') }}'}</td>
                <td>${parseFloat(service.default_price).toFixed(2)}</td>
                <td>${service.duration_min}</td>
              </tr>
            `;
                    });

                    contentDiv.innerHTML = `
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>{{ __('messages.service') }}</th>
                  <th>{{ __('messages.category') }}</th>
                  <th>{{ __('messages.price') }}</th>
                  <th>{{ __('messages.duration') }}</th>
                </tr>
              </thead>
              <tbody>
                ${tableRows}
              </tbody>
            </table>
          `;
                } else {
                    contentDiv.innerHTML = `<div class="text-center text-muted"><p>{{ __('messages.no_services_available') }}</p></div>`;
                }
            })
            .catch(() => {
                contentDiv.innerHTML = `<div class="text-center text-danger"><p>{{ __('messages.error_loading_services') }}</p></div>`;
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const pricingModal = document.getElementById('pricingModal');

        if (pricingModal) {
            pricingModal.addEventListener('hidden.bs.modal', function () {
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';
                document.body.style.overflow = '';

                const contentDiv = document.getElementById('pricingTable');
                if (contentDiv) {
                    contentDiv.innerHTML = `
            <div class="text-center">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">{{ __('messages.loading_services') }}</span>
              </div>
              <p class="mt-2">{{ __('messages.loading_services') }}</p>
            </div>
          `;
                }
            });
        }
    });

    function showServiceDetails(serviceId) {
        const modal = new bootstrap.Modal(document.getElementById('serviceDetailsModal'));
        const contentDiv = document.getElementById('serviceDetailsContent');

        contentDiv.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">{{ __('messages.loading_service_details') }}</span>
        </div>
        <p class="mt-2">{{ __('messages.loading_service_details') }}</p>
      </div>
    `;

        modal.show();

        fetch(`/api/v1/services/${serviceId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status && data.data) {
                    const service = data.data;
                    contentDiv.innerHTML = `
            <div class="row">
              <div class="col-md-6">
                <img src="${service.feature_image}" alt="${service.name}" class="img-fluid rounded" style="max-height: 300px; object-fit: cover;">
              </div>
              <div class="col-md-6">
                <h4 class="text-primary mb-3">${service.name}</h4>
                <p class="text-muted mb-3">${service.description || '{{ __('messages.no_description_available') }}'}</p>
                <div class="row mb-3">
                  <div class="col-6">
                    <strong>{{ __('messages.price_label') }}</strong><br>
                    <span class="text-primary h5">SR ${parseFloat(service.default_price).toFixed(2)}</span>
                  </div>
                  <div class="col-6">
                    <strong>{{ __('messages.duration_label') }}</strong><br>
                    <span class="text-muted">${service.duration_min} {{ __('messages.minutes') }}</span>
                  </div>
                </div>
                <div class="mb-3">
                  <strong>{{ __('messages.category') }}</strong><br>
                  <span class="badge bg-secondary">${service.category ? service.category.name : '{{ __('messages.general') }}'}</span>
                </div>
                ${service.sub_category ? `
                <div class="mb-3">
                  <strong>{{ __('messages.sub_category') }}</strong><br>
                  <span class="badge bg-light text-dark">${service.sub_category.name}</span>
                </div>` : ''}
              </div>
            </div>
          `;
                } else {
                    contentDiv.innerHTML = `<div class="text-center text-danger"><p>{{ __('messages.error_loading_services') }}</p></div>`;
                }
            })
            .catch(() => {
                contentDiv.innerHTML = `<div class="text-center text-danger"><p>{{ __('messages.error_loading_services') }}</p></div>`;
            });
    }
</script>
