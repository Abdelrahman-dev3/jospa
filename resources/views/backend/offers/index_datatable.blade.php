@extends('backend.layouts.app')

@section('title')
 {{ __($module_action) }} {{ __($module_title) }}
@endsection

@push('after-styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .form-label {
            font-weight: 600;
        }
        .required:after {
            content:" *";
            color:red;
        }
        .preview-img {
            max-width: 200px;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }
        .flatpickr-calendar.arrowTop:after {
            border-bottom-color: #CF9233;
        }
        .flatpickr-calendar.arrowBottom:after {
            border-top-color: #CF9233;
        }
        .flatpickr-months .flatpickr-month {
            background: #CF9233;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: #CF9233;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month {
            background-color: #CF9233;
        }
        .flatpickr-weekdays {
            background: #CF9233;
        }
        span.flatpickr-weekday {
            background: #CF9233;
        }
        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected.inRange,
        .flatpickr-day.startRange.inRange,
        .flatpickr-day.endRange.inRange,
        .flatpickr-day.selected:focus,
        .flatpickr-day.startRange:focus,
        .flatpickr-day.endRange:focus,
        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange:hover,
        .flatpickr-day.endRange:hover,
        .flatpickr-day.selected.prevMonthDay,
        .flatpickr-day.startRange.prevMonthDay,
        .flatpickr-day.endRange.prevMonthDay,
        .flatpickr-day.selected.nextMonthDay,
        .flatpickr-day.startRange.nextMonthDay,
        .flatpickr-day.endRange.nextMonthDay {
          background: #CF9233;
        }
        .flatpickr-day.selected.startRange + .endRange:not(:nth-child(7n+1)),
        .flatpickr-day.startRange.startRange + .endRange:not(:nth-child(7n+1)),
        .flatpickr-day.endRange.startRange + .endRange:not(:nth-child(7n+1)) {
          -webkit-box-shadow: -10px 0 0 #CF9233;
                  box-shadow: -10px 0 0 #CF9233;
        }
        .flatpickr-day.week.selected {
          -webkit-box-shadow: -5px 0 0 #CF9233, 5px 0 0 #CF9233;
                  box-shadow: -5px 0 0 #CF9233, 5px 0 0 #CF9233;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-header text-white">
                    <h4 class="mb-0"><i class="fa fa-plus-circle"></i> {{ __('إضافة عرض جديد') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('ouroffersections.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label required">{{ __('messagess.discount_type') }}</label>
                            <select name="discount_type" class="form-control" required>
                                <option value="percentage">{{ __('messagess.percentage') }}</option>
                                <option value="fixed">{{ __('messagess.fixed') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">{{ __('messagess.discount_value') }}</label>
                            <input type="number" name="discount_value" class="form-control" placeholder="مثال: 50" required>
                        </div>

                        {{-- الوصف (JSON لغتين) --}}
                        <div class="mb-3">
                            <label class="form-label required">{{ __('الوصف بالعربية') }}</label>
                            <textarea name="description[ar]" rows="3" class="form-control" placeholder="مثال: خصم على تنظيف البشرة"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">{{ __('messagess.offer_description') }}</label>
                            <textarea name="description[en]" rows="3" class="form-control" placeholder="Example: Discount on skin cleaning"></textarea>
                        </div>

                        {{-- تاريخ البداية والنهاية باستخدام Flatpickr --}}
                        <div class="mb-3">
                            <label class="form-label required">{{ __('messagess.offer_duration') }}</label>
                            <input type="text" id="offer-daterange" class="form-control" name="date_range" placeholder="{{ __('messagess.select_date_range') }}" required>
                        </div>

                        {{-- اللون --}}
                        <div class="mb-3">
                            <label class="form-label">{{ __('messagess.color') }}</label>
                            <input type="color" name="color" class="form-control form-control-color" value="#c68b2c">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('messagess.image') }}</label>
                            <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(event)">
                            <div class="preview-wrapper" style="position: relative; display: inline-block;">
                                <img id="preview" class="preview-img"/>
                                <div id="preview-overlay" 
                                     style="position: absolute; top:0; left:0; width:100%; height:100%; 
                                            background: rgba(0, 0, 0, 0.55); border-radius:10px; display:none;">
                                </div>
                            </div>
                        </div>

                        {{-- الرابط --}}
                        <div class="mb-3">
                            <label class="form-label">{{ __('messagess.link') }}</label>
                            <input type="url" name="link" class="form-control" placeholder="https://example.com">
                        </div>

                        {{-- overlay --}}
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="overlay" value="1" id="overlayCheck">
                            <label class="form-check-label" for="overlayCheck">{{ __('messagess.overlay') }}</label>
                        </div>

                        <div class="d-flex justify-content-end">
                            @hasPermission('store_offers')
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fa fa-save"></i>{{ __('messagess.submit') }}
                                </button>
                            @endhasPermission
                            <a href="{{ route('app.offers') }}" class="btn btn-secondary ms-2">
                                <i class="fa fa-arrow-left"></i> {{ __('messagess.back') }}
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ar.js"></script>
        <!-- JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Flatpickr لتحديد فترة العرض
        flatpickr("#offer-daterange", {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: "ar", // لو اللغة الأساسية عندك عربية
            minDate: "today",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    let form = instance.element.form;

                    // إنشاء hidden input لتاريخ البداية
                    let startInput = document.createElement('input');
                    startInput.type = 'hidden';
                    startInput.name = 'start_date';
                    startInput.value = instance.formatDate(selectedDates[0], "Y-m-d");

                    // إنشاء hidden input لتاريخ النهاية
                    let endInput = document.createElement('input');
                    endInput.type = 'hidden';
                    endInput.name = 'end_date';
                    endInput.value = instance.formatDate(selectedDates[1], "Y-m-d");

                    // إضافة للـ form
                    form.appendChild(startInput);
                    form.appendChild(endInput);
                }
            }
        });

        document.getElementById('overlayCheck').addEventListener('change', function() {
            let overlay = document.getElementById('preview-overlay');
            if (this.checked) {
                overlay.style.display = 'block';
            } else {
                overlay.style.display = 'none';
            }
        });

        // معاينة صورة العرض
        function previewImage(event) {
            let reader = new FileReader();
            reader.onload = function(){
                let output = document.getElementById('preview');
                let overlay = document.getElementById('preview-overlay');
                output.src = reader.result;
                output.style.display = 'block';
                if (document.getElementById('overlayCheck').checked) {
                    overlay.style.display = 'block';
                } else {
                    overlay.style.display = 'none';
                }
            };
            reader.readAsDataURL(event.target.files[0]);
        }
        
        @if(session('success'))
            toastr.success("{{ session('success') }}");
        @endif
    
        @if(session('error'))
            toastr.error("{{ session('error') }}");
        @endif
    </script>
@endpush
