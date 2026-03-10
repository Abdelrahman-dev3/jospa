<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $category->name }} - {{ __('messagess.details') }}</title>
    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        .service-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-5px);
        }
        .category-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            display: inline-block;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .booking-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .booking-btn:hover {
            background: #a67c3f;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        .price-badge {
            background: var(--primary-color);
            color: #fff;
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .category-badge {
            background: #f5f5f5;
            color: var(--primary-color);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .service-info {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .service-info i {
            color: var(--primary-color);
            width: 20px;
            margin-right: 0.5rem;
        }
        .service-image {
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            width: 100%;
            height: 200px;
            object-fit: cover;
            margin-bottom: 1rem;
        }
        .category-description {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .category-image {
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            width: 100%;
            max-height: 300px;
            object-fit: cover;
        }
        @media (max-width: 768px) {
            .category-title { font-size: 1.8rem; }
            .service-card { padding: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Lightning Progress Bar -->
    @include('components.frontend.progress-bar')

    <!-- Hero/Header Section (like About page) -->
    <div class="position-relative" style="height: 35vh; min-height: 220px;">
        <img src="{{ asset('images/frontend/slider1.webp') }}" alt="Category Hero" class="w-100 h-100" style="object-fit: cover;">
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: rgba(0,0,0,0.35);"></div>
        @include('components.frontend.navbar')
    </div>

    <main class="container" style="margin-top: -60px; z-index: 2; position: relative;">
        <!-- Title and Booking Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="category-title">{{ $category->name }}</h1>

        </div>

        <!-- Description and Image Section -->
        <div class="row mb-5">
            <div class="col-lg-8">
                <div class="category-description">
                    <h4 class="mb-3" style="color: var(--primary-color);">
                        <i class="fas fa-info-circle me-2"></i>About {{ $category->name }}
                    </h4>
                    <p class="mb-0">{{ $category->description ?? 'Explore our comprehensive ' . $category->name . ' services designed to meet your needs with professional care and attention to detail.' }}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <img src="{{ asset('images/frontend/slider' . (rand(1, 3)) . '.webp') }}"
                     alt="{{ $category->name }}"
                     class="category-image">
            </div>
        </div>

        <!-- Services Section -->
        <div class="mb-4">
            <h3 class="mb-4" style="color: var(--primary-color);">
                <i class="fas fa-list me-2"></i>Our {{ $category->name }} Services
            </h3>
        </div>

        @if($category->services && $category->services->count() > 0)
            <div class="row g-4">
                @foreach($category->services as $service)
                    <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                        <div class="service-card">
                            <img src="{{ $service->feature_image ?? asset('images/frontend/slider1.webp') }}"
                                 alt="{{ $service->name }}"
                                 class="service-image">

                            <h5 class="mb-3">{{ $service->name }}</h5>

                            @if($service->description)
                                <p class="text-muted small mb-3">{{ Str::limit($service->description, 100) }}</p>
                            @endif

                            <div class="price-badge" data-bs-toggle="modal" data-bs-target="#pricingModal">
                                SR {{ number_format($service->default_price ?? 0, 2) }}
                            </div>

                            <div class="service-info">
                                <i class="fas fa-clock"></i>
                                <span>{{ $service->duration_min ?? 0 }} {{ __('messagess.minutes') }}</span>
                            </div>

                            @if($service->sub_category)
                                <div class="service-info">
                                    <i class="fas fa-tags"></i>
                                    <span class="category-badge">{{ $service->sub_category->name }}</span>
                                </div>
                            @endif

                            <!--@if($service->branches && $service->branches->count() > 0)-->
                            <!--    <div class="service-info">-->
                            <!--        <i class="fas fa-map-marker-alt"></i>-->
                            <!--        <span class="small">{{ $service->branches->pluck('name')->implode(', ') }}</span>-->
                            <!--    </div>-->
                            <!--@endif-->

                            <div class="mt-3">
                                <a href="{{ route('frontend.service.details', $service->id) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-info-circle me-1"></i>{{ __('messagess.view_details') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <p class="text-muted">{{ __('messagess.no_services_in_category') }}</p>
            </div>
        @endif
    </main>

    <!-- Pricing Modal -->
    <div class="modal fade" id="pricingModal" tabindex="-1" aria-labelledby="pricingModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="pricingModalLabel">{{ $category->name }} - {{ __('messagess.category_services_pricing') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.close') }}"></button>
          </div>
          <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
            @if($category->services && $category->services->count() > 0)
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>{{ __('messages.service') }}</th>
                      <th>{{ __('category.sub_category') }}</th>
                      <th>{{ __('messagess.price_sr') }}</th>
                      <th>{{ __('messagess.duration_minutes') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($category->services as $service)
                        <tr>
                          <td>{{ $service->name }}</td>
                          <td>{{ $service->sub_category ? $service->sub_category->name : '-' }}</td>
                          <td>{{ number_format($service->default_price, 2) }}</td>
                          <td>{{ $service->duration_min }}</td>
                        </tr>
                    @endforeach
                  </tbody>
                </table>
            @else
                <div class="text-center text-muted">
                    <p>{{ __('messagess.no_services_available') }}</p>
                </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    @include('components.frontend.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800 });

        // Fix modal backdrop issue
        document.addEventListener('DOMContentLoaded', function() {
            const pricingModal = document.getElementById('pricingModal');

            if (pricingModal) {
                pricingModal.addEventListener('hidden.bs.modal', function () {
                    // Remove any remaining backdrop
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());

                    // Remove modal-open class from body
                    document.body.classList.remove('modal-open');
                    document.body.style.paddingRight = '';
                    document.body.style.overflow = '';
                });
            }
        });
    </script>
</body>
</html>
