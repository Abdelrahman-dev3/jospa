@php
use Illuminate\Support\Str;
@endphp

@extends('layouts.frontend-page', ['showProgress' => true, 'topSpacerHeight' => '71.4px', 'bottomSpacerHeight' => '71.4px'])

@section('head')
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') | {{ app_name() }}</title>
    
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
  <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
  @if (language_direction() == 'rtl')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
  @stack('after-styles')
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('pages-css/cart.css') }}">
  <style>
    @media (max-width: 768px) {
        .order-summary{
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
    }

    .cart-payment-notice{
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(191, 148, 86, 0.18);
        border-radius: 26px;
        padding: 22px 24px;
        margin-bottom: 22px;
        background:
            radial-gradient(circle at top right, rgba(191, 148, 86, 0.22), transparent 42%),
            linear-gradient(135deg, #fffaf2 0%, #ffffff 54%, #f7efe2 100%);
        box-shadow: 0 18px 42px rgba(191, 148, 86, 0.12);
    }

    .cart-payment-notice::before{
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 7px;
        background: linear-gradient(180deg, #bf9456 0%, #8d6938 100%);
    }

    .cart-payment-notice__wrap{
        display: flex;
        align-items: flex-start;
        gap: 18px;
    }

    .cart-payment-notice__icon{
        flex: 0 0 58px;
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 24px;
        background: linear-gradient(135deg, #bf9456 0%, #8d6938 100%);
        box-shadow: 0 14px 28px rgba(141, 105, 56, 0.22);
    }

    .cart-payment-notice__badge{
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 999px;
        margin-bottom: 10px;
        background: rgba(191, 148, 86, 0.12);
        color: #8d6938;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.2px;
    }

    .cart-payment-notice__title{
        margin: 0 0 8px;
        color: #2c2418;
        font-size: 20px;
        font-weight: 800;
    }

    .cart-payment-notice__text{
        margin: 0;
        color: #5f5345;
        line-height: 1.9;
        font-size: 15px;
    }

    .cart-payment-notice__text strong{
        color: #8d6938;
        font-weight: 800;
    }

    @media (max-width: 767px) {
        .cart-payment-notice{
            border-radius: 22px;
            padding: 18px 16px;
        }

        .cart-payment-notice__wrap{
            gap: 14px;
        }

        .cart-payment-notice__icon{
            width: 48px;
            height: 48px;
            flex-basis: 48px;
            border-radius: 15px;
            font-size: 20px;
        }

        .cart-payment-notice__title{
            font-size: 17px;
        }

        .cart-payment-notice__text{
            font-size: 14px;
        }
    }
  </style>
@endsection

@section('content')
<div class="container py-5">
  @if($services->count())
    <div class="cart-payment-notice">
      <div class="cart-payment-notice__wrap">
        <div class="cart-payment-notice__icon">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div>
          <div class="cart-payment-notice__badge">
            <i class="fa-solid fa-bell"></i>
            تنبيه مهم قبل الدفع
          </div>
          <h5 class="cart-payment-notice__title">احجزي الآن وأكملي الدفع خلال المهلة</h5>
          <p class="cart-payment-notice__text">
            تنبيه: سيتم إلغاء وحذف الحجز تلقائياً بعد مرور <strong>10 دقائق</strong> من حجزه إذا لم يتم إكمال عملية الدفع بنجاح.
          </p>
        </div>
      </div>
    </div>
  @endif
  <div class="row g-4">
    @if($services->count() || $products->count() || $gifts->count() ) 
    <div class="col-lg-8">
      <div class="order-summary p-3">
        <table class="table align-middle">
          <thead>
            <tr style="background-color: red;">
                <th class="d-none-mob" style="padding:16px 20px;font-weight:bold;">{{ __('messagess.product') }}</th>
                <th class="d-none-mob" style="padding:16px 20px;font-weight:bold;">{{ __('messagess.price') }}</th>
                <th class="d-none-mob" style="padding:16px 20px;font-weight:bold;">{{ __('messagess.discount_coupon') }}</th>
                <th style="padding:16px 20px;font-weight:bold;">{{ __('messagess.final_price') }}</th>
            </tr>
          </thead>
          <tbody>
          @foreach($services as $item)
              @foreach($item->services as $service)
                <tr>
                  <td class="d-flex align-items-center gap-2">
                    <div class="product-img"><i class="bi bi-person"></i></div>
                    <div class="text-start">
                      <strong>
                        {{ \Illuminate\Support\Str::limit($service->service_name, 12) }}
                        <i class="bi bi-chevron-left"></i> 
                        <i class="bi bi-chevron-left" style="margin: 0 -9px;"></i>
                        <i class="bi bi-chevron-left"></i>
                        {{ \Illuminate\Support\Str::limit($service->service_name, 12) }}
                      </strong><br>
                      <small class="text-muted">{{ __('messagess.employee') }}: {{ $service->employee->full_name ?? '-' }}</small>
                    </div>
                  </td>
                  <td class="prc">
                    {{ $service->service_price }} {{ __('messagess.SR')}}
                  </td>
                  <td style="direction: rtl";>
                   @if ($service->coupon_code)
                     <input class="co-ser-in" type="text" value="{{ $service->coupon_code }}" disabled>
                     <button class="co-ser-disabled" disabled>{{ __('messagess.apply_coupon') }}</button>
                   @else
                     <input class="co-ser-in" type="text"  data-service-id="{{ $service->service_id }}" data-booking-id="{{ $item->id }}">
                     <button class="co-ser" onclick="checkCoupon(this)">{{ __('messagess.apply_coupon') }}</button>
                   @endif
                  </td>
                  
                  <td style="position: relative;font-weight: bold;">
                  @if($service->discount_amount && $service->discount_amount > 0)
                   {{ $service->service_price - $service->discount_amount }} {{ __('messagess.SR')}}
                  @else
                   {{ $service->service_price }} {{ __('messagess.SR')}}
                  @endif
                    <form action="{{ route('cart.destroy', $item->id) }}" method="post" style="position: absolute;top: 8px;left: 8px;">
                        @csrf
                        @method('DELETE')
                        <button class="service-delete" title="{{ __('messagess.delete_service') }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                  </td>
                </tr>
              @endforeach
          @endforeach
          @foreach($products as $item)
            <tr>
              <td class="d-flex align-items-center gap-2">
                <div class="product-img"><i class="bi bi-person"></i></div>
                <div class="text-start">
                    <strong>
                        {{ \Illuminate\Support\Str::limit($item->product->name, 12) }}
                    </strong>
                    <br>
                  <small class="text-muted">{{ __('booking.qty') }}: {{ $item->qty }}</small>
                </div>
              </td>
              <td class="prc">
                {{ $item->product->min_price ?? $item->product->max_price ?? 0}} {{ __('messagess.SR')}}
              </td>
              <!---->
              <td style="direction: rtl;">
               </td>
              
              <td style="position: relative;font-weight: bold;">
                {{ ($item->product->min_price ?? $item->product->max_price ?? 0) * $item->qty }} {{ __('messagess.SR')}}

                <form action="{{ route('p.cart.destroy', $item->id) }}" method="post" style="position: absolute;top: 8px;left: 8px;">
                    @csrf
                    @method('DELETE')
                    <button class="service-delete" title="{{ __('messagess.delete_service') }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
              </td>
            </tr>
          @endforeach
          @foreach($gifts as $item)
            <tr>
              <td class="d-flex align-items-center gap-2">
                <div class="product-img"><i class="bi bi-gift"></i></div>
                <div class="text-start">
                  <strong>
                      {{ __('messagess.giftcard') }}:
                      {{ Str::limit($item->sender_name, 12) }}
                      <i class="bi bi-chevron-left"></i>
                      <i class="bi bi-chevron-left" style="margin: 0 -9px;"></i>
                      <i class="bi bi-chevron-left"></i>
                      {{ Str::limit($item->recipient_name, 12) }}
                  </strong>
                </div>
              </td>
              <td class="prc">
                {{ $item->subtotal ?? 0}} {{ __('messagess.SR')}}
              </td>
              <!---->
              <td style="direction: rtl;">
               </td>
              
              <td style="position: relative;font-weight: bold;">
                {{ $item->subtotal ?? 0}} {{ __('messagess.SR')}}

                <form action="{{ route('g.cart.destroy', $item->id) }}" method="post" style="position: absolute;top: 8px;left: 8px;">
                    @csrf
                    @method('DELETE')
                    <button class="service-delete" title="{{ __('messagess.delete_service') }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>

        <div class="text-end mt-3">
        <form action="{{ route('cart.destroyAll') }}" method="post" >
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-delete"><i class="bi bi-trash"></i> {{ __('messages.delete_All') }} </button>
        </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4 side-bar"> 
      <div class="summary-box">
        <h6 class="text-center mb-3 border-bottom pb-4">{{ __('messagess.service_summary') }}</h6>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('messagess.services_included') }} :</span><span class="output">{{ $serviceCount }} {{ __('messagess.service') }}</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('messagess.products_included') }} :</span><span class="output">{{ $productCount }} {{ __('messagess.product') }}</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between fw-bold mb-3">
          <span>{{ __('messagess.T_P') }}:</span><span class="output"> {{$finalPrice}} {{ __('messagess.SR') }} </span>
        </div>
        <div class="w-100 d-flex justify-content-center">
        <a href="{{route('paymentMethods')}}" class="more-btn"><i class="bi bi-credit-card"></i>  {{ __('messagess.proceed_to_payment') }}</a>
        </div>
      </div>
    </div>
    @else
        <div class="cart-empty">
            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
            <p>{{ __('messagess.cart_empty_message') }}</p>
            <a href="{{ route('frontend.services') }}" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-right"></i> {{ __('messagess.browse_services') }}
            </a>
        </div>
    @endif
  </div>
</div>
<!-- Footer -->
<!-- Bootstrap Icons -->
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
<script>
    function checkCoupon(button) {
        const input = button.previousElementSibling;
        const couponCode = input.value.trim();
        const serviceId = input.dataset.serviceId;
        const bookingId = input.dataset.bookingId;
        
        if (!couponCode) {
            toastr.error("{{ __('messagess.enter_coupon_code') }}");
            return;
        }
        
        const url = `/api/validate-coupon?coupon_code=${encodeURIComponent(couponCode)}&service_id=${serviceId}&booking_id=${bookingId}`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.valid) { 
                    toastr.success("{{ __('messagess.coupon_applied') }}: " + " " + couponCode);
                    setTimeout(() => {
                        location.reload();
                    }, 700);
                } else {
                    toastr.error("{{ __('messagess.invalid_coupon_for_service') }}"); 
                }
            })
            .catch(() => { toastr.error("{{ __('messagess.error_occurred') }}");  });
    }
</script>
@endsection
