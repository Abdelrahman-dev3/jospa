@extends('backend.layouts.app')

@section('title')
{{ __('messages.contect_cards_list') }} 
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush


@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">
            <i class="c-icon cil-sms"></i>
            {{ __('taqnyat.sms_gateway_settings') }}
        </h4>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('store') }}">
            @csrf
            
            <!-- Enable/Disable SMS Gateway -->
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">
                    {{ __('taqnyat.enable_sms') }}
                </label>
                <div class="col-sm-9">
                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_taqnyat_sms" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_taqnyat_sms" name="is_taqnyat_sms" value="1" {{ setting('is_taqnyat_sms') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_taqnyat_sms">
                            {{ __('taqnyat.enable_sms_gateway') }}
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        {{ __('taqnyat.enable_sms_gateway_hint') }}
                    </small>
                </div>
            </div>
            <!-- Submit Button -->
            <div class="form-group row">
                <div class="col-sm-9 offset-sm-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="c-icon cil-save"></i>
                        {{ __('taqnyat.save_settings') }}
                    </button>
                </div>
            </div>
        </form>
                <!-- Test Message Section -->
                <hr>
                <div class="{{ setting('is_taqnyat_sms') ? '' : 'd-none' }}">
                <h6 class="mb-3">{{ __('taqnyat.test_message') }}</h6>
                <form action="{{ route('send-test') }}" method="POST">
                @csrf
                    <div class="form-group row">
                        <label for="testPhone" class="col-sm-3 col-form-label">
                            {{ __('taqnyat.phone_number') }}
                        </label>
                        <div class="col-sm-4">
                            <input type="text" class="form-control" id="testPhone" name="test_phone"
                                placeholder="{{ __('taqnyat.phone_number_placeholder') }}" value="{{ old('test_phone') }}">
                            <small class="form-text text-muted">
                                {{ __('taqnyat.phone_number_hint') }}
                            </small>
                        </div>
                        <div class="col-sm-5">
                            <button type="submit" class="btn btn-warning">
                                <i class="c-icon cil-send"></i>
                                {{ __('taqnyat.send_test_message') }}
                            </button>
                        </div>
                    </div>
                </form>
                </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
// Simple script to show/hide sections based on checkbox
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('is_taqnyat_sms');
    const messageSection = document.getElementById('messageTemplatesSection');
    
    function toggleSections() {
        if (checkbox.checked) {
            messageSection.classList.remove('d-none');
        } else {
            messageSection.classList.add('d-none');
        }
    }
    
    checkbox.addEventListener('change', toggleSections);
    toggleSections(); // Initial state
});
</script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    @if(session('success'))
        toastr.success("{{ session('success') }}");
    @endif

    @if(session('error'))
        toastr.error("{{ session('error') }}");
    @endif

    @if ($errors->any())
        @foreach ($errors->all() as $error)
            toastr.error("{{ $error }}");
        @endforeach
    @endif

    @if(session('warning'))
        toastr.warning("{{ session('warning') }}");
    @endif

    @if(session('info'))
        toastr.info("{{ session('info') }}");
    @endif
</script>

@endpush
