@extends('layouts.frontend-page', ['showProgress' => true, 'topSpacerHeight' => '17vh', 'bottomSpacerHeight' => '17vh'])

@section('head')
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') | {{ app_name() }}</title>
    
  <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
  <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
  <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
  <link rel="stylesheet" href="{{ asset('pages-css/complate-bookings.css') }}">
  
  @if (language_direction() == 'rtl')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
  
  @stack('after-styles')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
@endsection

@section('content')
<div class="container py-5">
  <div class="row g-4">
    <div class="col-lg-12">
      <div class="order-summary p-3">
        <table class="table align-middle">
          <div class="row g-4">
            @forelse ($gifts as $gift)
                <div class="col-lg-6">
                    <div class="card gift-card shadow-sm border-0 h-100">

                        <!-- Header -->
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <div>
                                <i class="bi bi-gift-fill text-primary me-1"></i>
                                <strong>{{ $gift->delivery_method }}</strong>
                                
                                @if ($gift->delivery_method == __('gift.digital_card'))
                                    :<strong>{{ $gift->balance }}</strong>
                                @endif
                            </div>

                            @if ($gift->payment_status)
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i> {{ __('gift.paid') }}
                                </span>
                            @else
                                <span class="badge bg-danger">
                                    <i class="bi bi-x-circle me-1"></i> {{ __('gift.unpaid') }}
                                </span>
                            @endif
                        </div>

                        <!-- Body -->
                        <div class="card-body">

                            <div class="row mb-2">
                                <div class="col-6 text-muted">👤 {{ __('gift.sender') }}</div>
                                <div class="col-6 fw-semibold">{{ $gift->sender_name }}</div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-6 text-muted">📞 {{ __('gift.sender_phone') }}</div>
                                <div class="col-6">{{ $gift->sender_phone }}</div>
                            </div>

                            <hr>

                            <div class="row mb-2">
                                <div class="col-6 text-muted">🎁 {{ __('gift.recipient') }}</div>
                                <div class="col-6 fw-semibold">{{ $gift->recipient_name }}</div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-6 text-muted">📞 {{ __('gift.recipient_phone') }}</div>
                                <div class="col-6">{{ $gift->recipient_phone }}</div>
                            </div>


                            @if(!empty($gift->message))
                                <div class="row mb-2">
                                    <div class="col-6 text-muted">{{ __('messagess.optional_msg') }}</div>
                                    <div class="col-6">{{ $gift->message }}</div>
                                </div>
                            @endif

                            <hr>

                            <!-- Coupons -->
                            <div class="mb-3">
                                <div class="text-muted mb-1">💳 {{ __('gift.coupons') }}</div>
                                
                                @if (!empty($gift->coupons) && is_array($gift->coupons))
                                    <ul class="list-group list-group-flush">
                                        @foreach ($gift->coupons as $coupon)
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>{{ $coupon['name'] ?? '-' }}</span>
                                                <strong>{{ $coupon['price'] ?? 0 }} {{ __('gift.currency') }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted">{{ __('gift.no_coupons') }}</span>
                                @endif
                            </div>

                            <!-- Services -->
                            <div class="mb-3">
                                <div class="text-muted mb-1">{{ __('gift.services') }}</div>
                                @foreach($gift->services_list as $service)
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>{{ $service->name }}</span>
                                        <strong>{{ $service->default_price }} {{ __('gift.currency') }}</strong>
                                    </li>
                                @endforeach
                            </div>

                            <!-- Packages -->
                            <div class="mb-3">
                                <div class="text-muted mb-1">{{ __('gift.packages') }}</div>
                                @if(!empty($packageIds))
                                    <ul class="list-group list-group-flush">
                                        @foreach($packageIds as $packageId)
                                            @php
                                                $package = Modules\Package\Models\Package::find($packageId);
                                            @endphp
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>{{ $package->name }} </span>
                                                <strong>{{ $package->package_price }} {{ __('gift.currency') }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="text-muted">💰 {{ __('gift.total') }}</span>
                                <span class="fs-5 fw-bold text-primary">{{ number_format($gift->subtotal, 2) }} {{ __('gift.currency') }}</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="card-footer bg-white border-top d-flex justify-content-between small text-muted">
                            <span>
                                <i class="bi bi-calendar-event me-1"></i>
                                {{ $gift->created_at->format('Y-m-d') }}
                            </span>

                            <span>
                                {{ __('gift.id') }}: #{{ $gift->id }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5">
                    <i class="bi bi-gift fs-1 mb-3"></i>
                    <div>{{ __('gift.no_gifts') }}</div>
                </div>
            @endforelse
          </div>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<!-- Scripts -->
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    @if (session('success'))
        toastr.success("{{ session('success') }}");
    @endif
    
    @if (session('error'))
        toastr.error("{{ session('error') }}");
    @endif
</script>
@endsection
