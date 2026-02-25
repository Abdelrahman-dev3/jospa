@extends('layouts.frontend-page', ['showProgress' => true, 'topSpacerHeight' => '71.4px', 'bottomSpacerHeight' => '71.4px'])

@section('head')
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') | {{ app_name() }}</title>
    
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  
  
  <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
  <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
  
  @if (language_direction() == 'rtl')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
  
  @stack('after-styles')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="{{ asset('pages-css/my-bookings.css') }}">
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
                <th style="padding:16px 20px;"></th>
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
                  <td style="color: #FF473E; font-weight: bold; cursor: pointer;" data-booking-id="{{$booking->id}}" data-bs-toggle="modal" data-bs-target="#cancelModal">
                      {{ __('messagess.cancellation_of_reservation') }}
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
<!-- Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cancelModalLabel">{{ __('messages.cancel_reservation_reason') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>

      <div class="modal-body">
        <form id="cancelForm">
          <p>{{ __('messages.please_select_reason') }}</p>
          @foreach($reasons as $reason )
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="reason" value="{{$reason->id}}" id="reason{{$reason->id}}">
            <label class="form-check-label" for="reason{{$reason->id}}">{{$reason->name[app()->getLocale()]}}</label>
          </div>
          @endforeach

        <div id="reasonError" class="text-danger mt-2" style="display: none;">
          {{ __('messages.reason_required') }}
        </div>
    </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          {{ __('messages.cancel') }}
        </button>
        
        <button type="button" class="btn btn-danger" id="confirmCancel">
          {{ __('messages.confirm_cancel') }}
        </button>
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
<script>
let selectedBookingId = null;

const cancelModal = document.getElementById('cancelModal');
cancelModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  selectedBookingId = button.getAttribute('data-booking-id');
});

document.getElementById('confirmCancel').addEventListener('click', function() {
  const checkboxes = document.querySelectorAll('#cancelForm input[name="reason"]:checked');
  const error = document.getElementById('reasonError');

  if (checkboxes.length === 0) {
    error.style.display = 'block';
    return;
  } else {
    error.style.display = 'none';
  }

  const selectedReasons = Array.from(checkboxes).map(cb => cb.value);

  if (!selectedBookingId) {
    console.error('❌ لم يتم تحديد رقم الحجز');
    return;
  }

  fetch(`/booking/cancel/${selectedBookingId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      reasons: selectedReasons
    })
  })
  .then(res => res.json())
  .then(data => {
    console.log(data);
    alert('✅ تم إلغاء الحجز بنجاح');
    const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
    modal.hide();
    location.reload();

  })
  .catch(err => console.error('❌ خطأ في الإلغاء:', err));
});
</script>
@endsection
