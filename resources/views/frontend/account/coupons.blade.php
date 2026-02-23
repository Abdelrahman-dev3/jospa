@extends('layouts.frontend-page', ['showProgress' => true, 'topSpacerHeight' => '71.4px', 'showBottomNotifications' => true])

@section('head')
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') | {{ app_name() }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
    
  <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
  <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
  
  @if (language_direction() == 'rtl')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
  @stack('after-styles')
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="{{ asset('pages-css/coupon.css') }}">
@endsection

@section('content')
<div class="coupon-section">
  <div class="container">
    <div class="row justify-content-center">
      @foreach($coupons as $coupon)
      <div class="col-md-6">
        <div class="coupon-card">
          <h6 class="coupon-title">
            <i class="fa-solid fa-ticket"></i> {{__('messagess.discount_coupon')}} 
          </h6>
          <p class="coupon-text">{{$coupon->promotion->description}}</p>
          <p class="coupon-text discount_code">
            {{__('messagess.discount_code')}} {{$coupon->coupon_code}}
          </p>
          <div class="coupon-footer">
            <span>{{__('messagess.valid_until')}} {{$coupon->promotion->end_date_time}}</span>
            <span class="copy-code" data-code="{{$coupon->coupon_code}}" style="cursor: pointer;">
              {{__('messagess.copy_code')}} <i class="fa-regular fa-copy"></i>
            </span>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>

<!-- Footer -->
<!-- Bootstrap Icons -->
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const copyButtons = document.querySelectorAll('.copy-code');

  copyButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const code = btn.getAttribute('data-code');
      navigator.clipboard.writeText(code).then(() => {
        createNotify({
          title: '{{ __("messagess.copy_code") }}',
          desc: '{{ app()->getLocale() == "ar" ? "تم نسخ الكود بنجاح!" : "Code copied successfully!" }}'
        });
      }).catch(err => {
        console.error('خطأ أثناء النسخ:', err);
      });
    });
  });
});
</script>
@endsection
