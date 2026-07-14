@extends('layouts.frontend-page', ['showNavbar' => true, 'topSpacerHeight' => null, 'showFooter' => false, 'htmlLang' => $currentLocale, 'htmlDir' => $currentLocale === 'ar' ? 'rtl' : 'ltr', 'bodyDir' => $currentLocale === 'ar' ? 'rtl' : 'ltr', 'bodyLang' => $currentLocale])

@section('head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messagess.giftcard') }} - JOSPA</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <link rel="stylesheet" href="{{ asset('pages-css/gift.css') }}?v={{ filemtime(public_path('pages-css/gift.css')) }}">
@endsection

@section('content')
<div class="gift-page-shell">
    <div class="d-none d-md-block proh">
        <div class="slider-track">
            @if($ads->count())
                @foreach($ads as $key => $item)
                    <div class="slider-item">
                        <img src="{{ asset($item->image) }}" alt="Image 1">
                    </div>
                @endforeach
            @endif
        </div>
    </div>


    <div class="main-container">
        <div class="gift-card-container">
            <h1 class="page-title">{{ __('messagess.perfect_gift') }}</h1>
            <p class="page-subtitle">{{ __('messagess.choose_your_gift') }}</p>
            <!-- Right side - Form -->
            <div class="form-container">
                <form method="GET" action= "{{ route('gift.create') }}" id="Form">
                    @csrf
                    <!-- Delivery Method Section -->
                    <div class="section delivery-section">
                        <h3 class="section-title fw-bold"><i class="fa-solid fa-truck-fast"></i> {{ __('messagess.delivery_method') }}</h3>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="delivery_method" value="center_pickup" checked>
                                <span class="radio-indicator"></span>
                                <span class="radio-text">{{ __('messagess.traditional_gift_card') }}</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="delivery_method" value='electronic_card'>
                                <span class="radio-indicator"></span>
                                <span class="radio-text">{{ __('messagess.email_delivery') }}</span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" id="userId" value="{{ auth()->check() ? auth()->id() : '' }}">
                    <!-- Card Information Section -->
                    <div class="section card-info-section">
                        <h3 class="section-title fw-bold"><i class="fa-solid fa-address-card"></i> {{ __('messagess.card_info') }}</h3>

                        <div class="input-row">
                            <div class="input-group">
                                <label class="input-label">{{ __('messagess.gifter_name') }}</label>
                                <input type="text" name="sender_name" value="{{old('sender_name')}}" class="form-input" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">{{ __('messagess.recipient_name') }}</label>
                                <input type="text" name="recipient_name" value="{{ old('recipient_name') }}" class="form-input" required>
                            </div>
                        </div>

                        <div class="input-row">
                            <div class="input-group">
                                <label class="input-label">{{ __('messages.sender_phone') }}</label>
                                <input type="tel" name="sender_phone" value="{{ old('sender_phone') }}" class="form-input" id="sender_phone">
                            </div>
                            <div class="input-group">
                                <label class="input-label">{{ __('messages.recipient_phone') }}</label>
                                <input type="tel" name="recipient_phone" value="{{ old('recipient_phone') }}" class="form-input" id="recipient_phone">
                            </div>
                        </div>

                        <div class="input-group full-width">
                            <label class="input-label">{{ __('messagess.optional_msg') }}</label>
                            <textarea class="form-textarea" name="optional_services" id="optional_services" maxlength="100">{{ old('optional_services') }}</textarea>
                        </div>
                    </div>

                    <!-- Services Selection -->
                    <div class="services-container">
                        <h3 class="section-title fw-bold"><i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('messagess.service_selection') }}</h3>
                        <input type="hidden" id="tlt" value="" name="subtotal">
                            <div class="container">
                                <div class="clickable-div" onclick="toggleList(this)">
                                    {{ __('messages.packages') }}
                                </div>
                                <div class="checkbox-list row">
                                    @foreach($packages as $index => $package)
                                    <div class="col-md-6">
                                        <div class="checkbox-item">
                                          <input type="checkbox" id="{{ $package->id }}" data-price="{{ $package->package_price }}" name="package_ids[]" value="{{ $package->id }}" {{ in_array($package->id, old('package_ids', [])) ? 'checked' : '' }} >
                                          <label for="{{ $package->id }}" style="display:flex;justify-content: space-between;margin: 0 6px;">
                                                <div>
                                                    <div class="package-name">
                                                        <span>{{ $package->name }}</span>
                                                    
                                                        <div class="tooltip-wrapper">
                                                            <i class="fas fa-question-circle info-icon"></i>
                                                    
                                                            <div class="custom-tooltip">
                                                                @foreach($package->service as $srv)
                                                                    <div>{{ $srv->service_name }}</div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <span>{{ $package->package_price }}  {{ __('messages.currency') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        <!-- 1 -->
                        @foreach ($subCategories as $sub)
                            <div class="container">
                                <div class="clickable-div" onclick="toggleList(this)">
                                    {{ $sub->name }}
                                </div>
                                <div class="checkbox-list row">
                                    @foreach ($sub->services as $service)
                                    <div class="col-md-6">
                                        <div class="checkbox-item">
                                          <input type="checkbox" id="service_{{ $service->id }}" name="requested_services[]" value="{{ $service->id }}" data-price="{{ $service->default_price }}"  {{ in_array($service->id, old('requested_services', [])) ? 'checked' : '' }}>
                                          <label for="service_{{ $service->id }}" style="display:flex;justify-content: space-between;margin: 0 6px;">
                                                <div>
                                                <span>{{ $service->name }}</span> 
                                                </div>
                                                <span>{{ $service->default_price }} {{ __('messages.currency') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    <div class="container">
                        <div class="clickable-div" onclick="toggleList(this)">
                            {{ __('messagess.Coupons') }}
                        </div>
                        <div class="checkbox-list row">
                        @foreach ($coupons as $index => $coupon)
                            <div class="col-md-6">
                                <div class="checkbox-item">
                                  <input type="checkbox" id="coupons_{{ $index }}" name="coupons[]" value='@json(["name" => $coupon["name"], "price" => $coupon["price"]])' data-price="{{ $coupon['price'] }}" {{ in_array($index, old('coupons', [])) ? 'checked' : '' }}>
                                  <label for="coupons_{{ $index }}" style="display:flex;justify-content: space-between;margin: 0 6px;">
                                        <div>
                                        <span>{{ $coupon['name'] }}</span> 
                                        </div>
                                        <span>{{ number_format($coupon['price'], 2, ',', '.') }} {{ __('messages.currency') }}</span>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    
                    </div>
                    <!-- Service Notes -->
                    <div class="service-notes">
                        <p class="notes-title"> {{ __('messagess.soft_notes') }}</p>
                        <ul class="notes-list">
                            <li>{{ __('messagess.note_1') }}</li>
                            <li>{{ __('messagess.note_2') }}</li>
                            <li>{{ __('messagess.note_3') }}</li>
                            <li>{{ __('messagess.note_4') }}</li>
                        </ul>
                    </div>

                    <!-- Pricing Section -->
                    <div class="pricing-summary">

                        <div style="display: flex;justify-content: space-between;margin-bottom: 10px;">
                            <h3>{{ __('messagess.total_amount') }}</h3>
                            <h3>SR<span id="displayAmount">0.00</span></h3>
                        </div>
                        <button type="submit" class="add-to-cart-button">
                            <i class="fas fa-shopping-cart ml-2"></i>
                            {{ __('messages.add_to_cart') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
        document.addEventListener('DOMContentLoaded', function () {
            const params = new URLSearchParams(window.location.search);
            const packId = params.get('pack');
    
            if (packId) {
                const checkbox = document.getElementById(packId);
                if (checkbox) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change'));
                }
            }
        });

        const userId = document.getElementById('userId').value || null;
        const Form = document.getElementById('Form');
        const optionalServicesField = document.getElementById('optional_services');

        optionalServicesField?.addEventListener('input', function() {
            if (this.value.length > 100) {
                this.value = this.value.slice(0, 100);
            }
        });
        
        Form.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const optionalServicesValue = optionalServicesField ? optionalServicesField.value.trim() : "";
            let errors = [];
            let allData = {
                delivery_method: document.querySelector('input[name="delivery_method"]:checked')?.value || "",
                sender_name: document.querySelector('input[name="sender_name"]').value.trim(),
                recipient_name: document.querySelector('input[name="recipient_name"]').value.trim(),
                sender_phone: document.querySelector('input[name="sender_phone"]').value.trim(),
                recipient_phone: document.querySelector('input[name="recipient_phone"]').value.trim(),
        
                requested_services: Array.from(
                    document.querySelectorAll('input[name="requested_services[]"]:checked')
                ).map(cb => cb.value),
        
                package_ids: Array.from(
                    document.querySelectorAll('input[name="package_ids[]"]:checked')
                ).map(cb => cb.value),
        
                coupons: Array.from(
                    document.querySelectorAll('input[name="coupons[]"]:checked')
                ).map(cb => JSON.parse(cb.value)),
        
                optional_services: optionalServicesValue,
                subtotal: document.getElementById('tlt').value,
                user_id: userId
            };
            
            if (!allData.delivery_method) errors.push("{{ __('messages.gift_card_delivery_method_required') }}");
            if (!allData.sender_name) errors.push("{{ __('messages.gift_card_sender_required') }}");
            if (!allData.recipient_name) errors.push("{{ __('messages.gift_card_recipient_required') }}");
            
            const saudiPhoneRegex = /^05\d{8}$/;
            
            if (!allData.sender_phone) {
                errors.push("{{ __('messages.gift_card_phone_required') }}");
            } else if (!saudiPhoneRegex.test(allData.sender_phone)) {
                errors.push("{{ __('messages.invalid_sender_phone') }}");
            }
            
            if (!allData.recipient_phone) {
                errors.push("{{ __('messages.gift_card_phone_required') }}");
            } else if (!saudiPhoneRegex.test(allData.recipient_phone)) {
                errors.push("{{ __('messages.invalid_recipient_phone') }}");
            }
            
            if (allData.requested_services.length < 1) {
                errors.push("{{ __('messages.gift_card_service_required') }}");
            }

            if (allData.optional_services.length > 100) {
                errors.push("{{ __('messages.gift_card_message_too_long') }}");
            }
            
            if (errors.length > 0) {
                errors.forEach(msg => toastr.error(msg));
                return;
            }
            
            if (errors.length == 0) {
                Form.submit();
            }
        });
        
        // drop down
        function toggleList($par) {
            const list = $par.nextElementSibling;
            list.classList.toggle('show');
            $par.classList.toggle('active');
        }
        // Image gallery functionality
        function switchMainImage(thumbnailElement) {
            const mainImage = document.getElementById('mainProductImage');
            const allThumbnails = document.querySelectorAll('.thumbnail-img');

            // Update main image source
            const newImageSrc = thumbnailElement.src.replace('w=100&h=80', 'w=600&h=400');
            mainImage.src = newImageSrc;

            // Update active thumbnail
            allThumbnails.forEach(thumb => thumb.classList.remove('active'));
            thumbnailElement.classList.add('active');
        }
        document.addEventListener("DOMContentLoaded", function () {
        const checkboxes = document.querySelectorAll('input[name="requested_services[]"]');
        const checkboxesPackages = document.querySelectorAll('input[name="package_ids[]"]');
        const checkboxesCoubons = document.querySelectorAll('input[name="coupons[]"]');
        console.log(checkboxesCoubons)
        const displayAmount = document.getElementById('displayAmount');

        function calculateTotal() {
            let total = 0;
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const price = parseFloat(checkbox.dataset.price || 0);
                    total += price;
                }
            });
            checkboxesPackages.forEach(checkbox => {
                if (checkbox.checked) {
                    const price = parseFloat(checkbox.dataset.price || 0);
                    total += price;
                }
            });
            checkboxesCoubons.forEach(checkbox => {
                if (checkbox.checked) {
                    const price = parseFloat(checkbox.dataset.price || 0);
                    total += price;
                }
            });
            displayAmount.textContent = total.toFixed(2);
            document.getElementById('tlt').value = total.toFixed(2);
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', calculateTotal);
        });
        checkboxesPackages.forEach(checkbox => {
            checkbox.addEventListener('change', calculateTotal);
        });
        checkboxesCoubons.forEach(checkbox => {
            checkbox.addEventListener('change', calculateTotal);
        });

        calculateTotal();
    });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        @if (session('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if (session('error'))
            toastr.error("{{ session('error') }}");
        @endif

        @if ($errors->any())
            @foreach ($errors->all() as $error)
                toastr.error("{{ $error }}");
            @endforeach
        @endif
    </script>
@endsection
