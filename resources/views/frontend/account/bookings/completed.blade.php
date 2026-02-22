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
          <thead>
            <tr style="background-color: red;">
                <th style="padding:16px 20px;font-weight:bold;">{{ __('messagess.product') }}</th>
                <th style="padding:16px 20px;font-weight:bold;">{{ __('messagess.price') }}</th>
                <th style="padding:16px 20px;font-weight:bold;">{{ __('messages.branch') }}</th>
                <th style="padding:16px 20px;font-weight:bold;">{{ __('profile.date') }}</th>
                <th style="padding:16px 20px;font-weight:bold;">{{ __('profile.time') }}</th>
            </tr> 
          </thead>
          <tbody>
            @foreach($bookings as $booking)
                @foreach($booking->services as $service)
                <tr>
                  <td class="d-flex align-items-center gap-2">
                    <div class="product-img"><i class="bi bi-person"></i></div>
                    <div class="text-start">
                      <strong>{{ $service->service_name }} <i class="bi bi-chevron-left"></i> <i class="bi bi-chevron-left" style="margin: 0 -9px;"></i> <i class="bi bi-chevron-left"></i> {{ $service->service_name }}</strong><br>
                      <small class="text-muted">{{ __('messagess.employee') }}: {{ $service->employee->full_name ?? '-' }}</small>
                    </div>
                  </td>

                  <td class="prc">
                    {{ $service->service->default_price ?? 0  }} {{ __('messagess.currency') }}
                  </td>
                  <td>
                        {{ $booking->branch->name ?? (app()->getLocale() == 'ar' ? 'حجز منزلي' : 'Home booking') }}
                  </td>
                  <td>
                    {{ \Carbon\Carbon::parse($booking->start_date_time)->format('d-m-Y') }}
                  </td>
                  <td>
                    {{ \Carbon\Carbon::parse($booking->start_date_time)->format('H:i') }}
                  </td>
                </tr>
                @endforeach
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
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
@endsection
