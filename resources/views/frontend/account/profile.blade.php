@extends('layouts.frontend-page', ['showProgress' => true, 'showTopNotifications' => true, 'topSpacerHeight' => '71.4px', 'showFooter' => false])

@section('head')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <title>@yield('title') | {{ app_name() }}</title>

    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    @if (language_direction() == 'rtl')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    @stack('after-styles')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="{{ asset('pages-css/profile.css') }}">
@endsection

@section('content')
<div class="container my-5">
        <div class="profile-card p-4">
            <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <img src="{{ $user->avatar ?? $user->profile_image }}" alt="صورة" class="profile-img">
            </div>
            <div class="col-md-7">
                <div class="row mb-3">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('messagess.name') }} :</label>
                        <input type="text" class="form-control" value="{{ $user->first_name }} {{ $user->last_name }}" readonly>
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('profile.mobile_number') }} :</label>
                        <div class="input-group">
                            <span class="input-group-text">+966</span>
                            <input type="text" class="form-control" value="{{ $user->mobile }}" readonly>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('profile.city') }} :</label>
                        <input type="text" class="form-control" value="{{ $user->city ?? ($user->address->city ?? '') }}" readonly>
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('profile.address') }}:</label>
                        <input type="text" class="form-control" value="{{ $user->address ?? ($user->address->address_line_1 ?? '') }}" readonly>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('profile.country') }} :</label>
                        <input type="text" class="form-control" value="{{ $user->country ?? __('profile.no_data') }}" readonly>
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('location.title') }} :</label>
                        <input type="text" readonly class="form-control" value=" {{ $user->country ?? ($user->address->country ?? '') }}-{{ $user->city ?? ($user->address->city ?? '') }}-{{ $user->address ?? ($user->address->address_line_1 ?? '') }}">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label class="form-label">{{ __('profile.email') }} :</label>
                        <input class="form-control" type="text" readonly value="{{ $user->email }}">
                    </div>
                </div>
            </div>
            <div class="col-md-1 H-editProfileModal">
                <a href="#" class="editProfileModal" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fas fa-edit"></i>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                @csrf
                    <button type="submit" class="btn logout-btn" title="{{__('profile.logout')}}"><i class="fa-solid fa-right-from-bracket"></i>  {{ __('messages.logout') }} </button>
                </form>
            </div>
        </div>
        </div>
    </div>
    <div class="stats-box">
        <div class="stat-item">
          <h6> <span class="stat-icon"><img src="{{ asset('images/icons/vesa.png') }}"></span>{{ __('profile.wallet') }}</h6>
          <div><span class="stat-value">{{$balance}}</span> <span class="stat-unit">{{ __('profile.currency') }}</span> <button type="button" style="border: none;background: #f0f8ff00;font-size: 18px;" data-bs-toggle="modal" data-bs-target="#depositModal">+</button></div>
        </div>
        <div class="stat-item">
          <h6><span class="stat-icon"><img src="{{ asset('images/icons/gift.png') }}"></span>{{ __('profile.loyalty_points') }}</h6>
          <div><span class="stat-value">{{ $points }}</span> <span class="stat-unit">{{ __('profile.point') }}</span></div>
        </div>
        <div class="stat-item">
            <a href="{{route('profile.coupon')}}">
              <h6> <span class="stat-icon"><img src="{{ asset('images/icons/ticket.png') }}"></span>{{ __('profile.discount_coupons') }}</h6>
              <div><span class="stat-value">{{$coupons}}</span> <span class="stat-unit">{{ __('profile.coupon') }}</span></div>
            </a>
        </div>
        <div class="stat-item">
            <a href="{{route('profile.my_bookings')}}">
              <h6> <span class="stat-icon"><img src="{{ asset('images/icons/clock.png') }}"></span>{{ __('profile.booked_services') }}</h6>
              <div><span class="stat-value">{{$pending}}</span> <span class="stat-unit">{{ __('profile.service') }}</span></div>
            </a>
        </div>
        <div class="stat-item">
            <a href="{{route('profile.complateBokkings')}}">
              <h6> <span class="stat-icon"><img src="{{ asset('images/icons/true.png') }}"></span>{{ __('profile.completed_services') }}</h6>
              <div><span class="stat-value">{{$completed}}</span> <span class="stat-unit">{{ __('profile.service') }}</span></div>
            </a>
        </div>
        
        <div class="stat-item">
            <a href="{{route('profile.complateGift')}}">
              <h6> <span class="stat-icon"><img src="{{ asset('images/icons/true.png') }}"></span>{{ __('messages.gift_cards_list') }}</h6>
              <div><span class="stat-value">{{$completedGift}}</span> <span class="stat-unit">{{ __('profile.gift') }}</span></div>
            </a>
        </div>
  </div>

    <!-- Modal -->
    <div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="depositModalLabel">{{ __('messages.deposit_wallet') }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.close') }}"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="amount" class="form-label">{{ __('messages.amount') }}</label>
                <input type="number" name="amount" min="1" class="form-control" id="amount" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('messages.close') }}</button>
              <button type="button" class="btn btn-success btn-sm" id="depositBtn">
                  {{ __('messages.deposit') }}
              </button>
            </div>
        </div>
      </div>
    </div>



     <div class="row" style="width: 87%;margin: 40px auto 60px auto;">
    <div class="col-lg-8 mb-4">
      <div class="rounded p-3" style="background: white;">
        <h5 style="padding: 10px;color: #BF9456;font-weight: bold;font-size: 21px;" class="text-center mb-3">{{ __('profile.my_transactions') }}</h5>
        <div class="table-head">
            <h5>{{ __('profile.service') }}</h5>
            <h5>{{ __('profile.service_location') }}</h5>
            <h5>{{ __('profile.date') }}</h5>
            <h5>{{ __('profile.service_status') }}</h5>
        </div>
        @forelse($bookings as $booking)
            @foreach($booking->services as $service)
                <div class="table-content">
                    <h5 title="{{$service->service_name ?? '---'}}">{{ \Illuminate\Support\Str::limit($service->service_name ?? '---', 14) }}</h5>
                    <h5>{{ $booking->branch->name ?? '---' }}</h5>
                    <h5>{{ \Carbon\Carbon::parse($booking->start_date_time)->format('Y-m-d') }}</h5>
                    @if($booking->status == 'pending')
                        <h5 style="color:#F7F316">
                            {{ __('messagess.booked') }}
                        </h5>
                    @elseif($booking->status == 'completed')
                        <h5 style="color:#1FAF38">
                            {{ __('messagess.completed') }}
                        </h5>
                    @elseif($booking->status == 'cancelled')
                        <h5 style="color:#FF473E">
                            {{ __('messagess.cancelled') }}
                        </h5>
                    @else
                        <h5 style="color:#999">
                            {{ $booking->status ?? '---' }}
                        </h5>
                    @endif
                </div>
            @endforeach
        @empty
            <h5 style="position: absolute;color: #979797;">{{ __('profile.no_bookings') }}</h5>
        @endforelse
      </div>
    </div>

    <div class="col-lg-4">
      @php
        $paymentMethods = [
          'card' => (int) \App\Models\Setting::get('tap_payment_method', 1),
          'tabby' => (int) \App\Models\Setting::get('tabby_payment_method', 1),
          'tamara' => (int) \App\Models\Setting::get('tamara_payment_method', 1),
        ];
      @endphp
      <div class="payment-box shadow-sm">
        <h6 class="mb-5" style="font-weight: bold;">{{ __('messages.payment_methods') }}</h6>
        <div class="row g-3 text-center">
          @if(($paymentMethods['card'] ?? 0) === 1)
            <div class="col-6">
              <img src="{{asset('images/icons/visa.png')}}" alt="Visa" class="img-fluid  rounded p-3" style="background: #F9F6F0;">
            </div>
            <div class="col-6">
              <img src="{{asset('images/icons/mada.png')}}" alt="Mada" class="img-fluid  rounded p-3" style="background: #F9F6F0;">
            </div>
          @endif
          @if(($paymentMethods['tamara'] ?? 0) === 1)
            <div class="col-6">
              <img src="{{asset('images/icons/tamara.png')}}" alt="تمارا" class="img-fluid border rounded p-3" style="background: #F9F6F0;">
            </div>
          @endif
          @if(($paymentMethods['tabby'] ?? 0) === 1)
            <div class="col-6">
              <img src="{{asset('images/icons/tabby.png')}}" alt="tabby" class="img-fluid  rounded p-3" style="background: #F9F6F0;">
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

    <div class="modal fade" style="z-index: 999999999999999999999;" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('profile.update' , auth()->user()->id) }}" enctype="multipart/form-data" id="profileEditForm">
          @csrf
          @method('PUT')
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="editProfileModalLabel">{{ __('profile.edit_profile') }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('profile.close') }}"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="mb-3 col-12 text-center">
                  <label for="profile_image" class="form-label d-block">{{ __('profile.profile_image') }}</label>
                  <input type="file" id="profile_image" name="profile_image" accept="image/*" class="form-control mx-auto" style="max-width:300px;">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.first_name') }}</label>
                  <input type="text" name="first_name" class="form-control" value="{{ $user->first_name }}">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.last_name') }}</label>
                  <input type="text" name="last_name" class="form-control" value="{{ $user->last_name }}">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.mobile_number') }}</label>
                  <input type="text" name="mobile" class="form-control" value="{{ $user->mobile }}" placeholder="+9665XXXXXXXX">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.email') }}</label>
                  <input type="email" name="email" class="form-control" value="{{ $user->email }}">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.address') }}</label>
                  <input type="text" name="address" class="form-control" value="{{ $user->address ?? ($user->address->address_line_1 ?? '') }}">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.city') }}</label>
                  <input type="text" name="city" class="form-control" value="{{ $user->city ?? ($user->address->city ?? '') }}">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.country') }}</label>
                  <input type="text" name="country" class="form-control" value="{{ $user->country ?? ($user->address->country ?? '') }}">
                </div>
                <div class="mb-3 col-6">
                  <label class="form-label">{{ __('profile.birth_date') }}</label>
                  <input type="date" name="date_of_birth" class="form-control" value="{{ $user->date_of_birth }}">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">{{ __('profile.save_changes') }}</button>
            </div>
          </div>
        </form>
      </div>
    </div>
@endsection

@section('scripts')
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


  <script>
    document.addEventListener("DOMContentLoaded", function () {
        @if(session('success'))
              createNotify({ title: '', desc: "{{ session('success') }}" });
        @endif

        @if(session('error'))
              createNotify({ title: '', desc: "{{ session('error') }}" });
        @endif

        @if($errors->any())
            @foreach($errors->all() as $error)
                  createNotify({ title: '', desc: "{{ $error }}" });
            @endforeach
        @endif
    });
  </script>
  <script>
  document.getElementById('profileEditForm').addEventListener('submit', function(e) {
    const mobileInput = document.querySelector('input[name="mobile"]');
    const mobile = mobileInput.value.trim();
    const saudiRegex = /^(05\d{8}|(\+9665\d{8}))$/;

    if (!saudiRegex.test(mobile)) {
        e.preventDefault(); 
        createNotify({ title: '', desc: "الرجاء إدخال رقم جوال سعودي صحيح (مثال: 05XXXXXXXX أو +9665XXXXXXXX)" });
        mobileInput.focus();
    }
  });
    document.getElementById('depositBtn').addEventListener('click', function() {
        let amount = document.getElementById('amount').value;
        if (!amount || amount <= 0) {
            alert('الرجاء إدخال مبلغ صحيح');
            return;
        }
        let url = `/add/balance-to-wallet?amount=${amount}`;
        window.location.href = url;
    });
  </script>
@endsection
