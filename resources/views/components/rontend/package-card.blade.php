<div class="position-relative rounded-4 overflow-hidden shadow" style="height: 260px;">
    <img src="{{ $image ?? asset('images/frontend/slider1.webp') }}" alt="{{ $name ?? 'Package' }}" class="w-100 h-100" style="object-fit: cover;">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(to top, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0.0) 100%);"></div>
    <!-- View Package badge -->
    <div class="position-absolute top-0 start-0 m-3 px-3 py-1 rounded-pill text-white"
         style="background: var(--primary-color); cursor: pointer;"
         data-bs-toggle="modal"
         data-bs-target="#packageModal"
         @if(isset($package_id)) onclick="showPackageDetails({{ $package_id }})" @endif>
        View Package
    </div>
    <!-- Price badge -->
    @if(isset($price))
        <div class="position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill text-white"
             style="background: rgba(0,0,0,0.7);">
            {{ $price }}
        </div>
    @endif
    <!-- Package info -->
    <div class="position-absolute start-50 translate-middle-x" style="bottom: 90px;">
        <div class="d-flex flex-column align-items-center text-center">
            <div class="text-white h5 mb-0 fw-bold">{{ $name ?? 'Package Name' }}</div>
            @if(isset($description))
                <div class="text-white-50 small mt-1">{{ $description }}</div>
            @endif
            @if(isset($services_count))
                <div class="text-white-50 small mt-1">
                    <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                    </svg>
                    {{ $services_count }} services included
                </div>
            @endif
        </div>
    </div>
    <!-- Details Button -->
    <div class="position-absolute start-50 translate-middle-x w-fit d-flex justify-content-center px-5" style="bottom: 25px;">
        @if(isset($package_id))
            <a href="#" class="btn rounded-pill py-2 text-center m-0 d-flex align-items-center justify-content-center text-white"
               style="background-color: var(--primary-color);"
               onclick="showPackageDetails({{ $package_id }})">Details</a>
        @else
            <a href="#" class="btn rounded-pill py-2 text-center m-0 d-flex align-items-center justify-content-center text-white"
               style="background-color: var(--primary-color);">Details</a>
        @endif
    </div>
</div>
