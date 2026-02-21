<div class="position-relative rounded-4 overflow-hidden shadow" style="height: 350px;">
    <img src="{{ $image ?? asset('images/frontend/slider1.webp') }}" alt="{{ $name ?? 'Service' }}" class="w-100 h-100" style="object-fit: cover;">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(to top, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0.0) 100%);"></div>

    <!-- Pricing badge -->
    <div class="position-absolute top-0 start-0 m-3 px-3 py-1 rounded-pill text-white"
         style="background: var(--primary-color); cursor: pointer;"
         data-bs-toggle="modal"
         data-bs-target="#pricingModal">
        {{ $price ?? 'Pricing' }}
    </div>

    <!-- Category badge -->
    @if(isset($category))
        <div class="position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill text-white"
             style="background: rgba(0,0,0,0.7);">
            {{ $category }}
        </div>
    @endif

    <!-- Service info -->
    <div class="position-absolute ms-5" style="bottom: 120px;">
        <div class="d-flex flex-column gap-2">
            <div class="text-white h5 mb-0 fw-bold">{{ $name ?? 'Service Name' }}</div>
            @if(isset($description))
                <div class="text-white-50 small">{{ $description }}</div>
            @endif
            @if(isset($duration))
                <div class="text-white-50 small">
                    <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                    </svg>
                    {{ $duration }}
                </div>
            @endif
        </div>
    </div>

    <!-- Buttons -->
    <div class="position-absolute start-50 translate-middle-x w-100 d-flex justify-content-center px-5" style="bottom: 25px;">
        <div class="d-flex gap-3 text-white w-100 justify-content-center">
            <a href="#" class="btn rounded-pill d-flex align-items-center justify-content-center gap-2 px-4 py-3 text-white col-6" style="font-size: 1rem; background-color: var(--primary-color);">
                <svg data-v-eec84f9e="" style="width: 20px" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="paper-plane" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path class="" fill="currentColor" d="M498.1 5.6c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480l0-83.6c0-4 1.5-7.8 4.2-10.8L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"></path></svg>
 {{ __('messages.details') }}
            </a>
            @if(isset($service_id))
                <a href="{{ route('frontend.service.details', $service_id) }}" class="btn btn-light rounded-pill px-4 py-2 col-6 text-center m-0 d-flex align-items-center justify-content-center">Details</a>
            @else
                <a href="#" class="btn btn-light rounded-pill px-4 py-2 col-6 text-center m-0 d-flex align-items-center justify-content-center">{{ __('messages.details') }}</a>
            @endif
        </div>
    </div>
</div>
