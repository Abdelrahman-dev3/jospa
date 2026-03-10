@extends('frontend::layouts.auth')

@section('title', __('auth.verify_code'))

@section('content')
<style>
    .main-otp-box {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 100px);
        padding: 40px 20px;
        background: #fff;
        font-family: 'Almarai', sans-serif;
    }

    .sub-otp-box {
        background: #F9F6F0;
        padding: 40px 30px;
        width: 100%;
        max-width: 556px;
        border-radius: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        position: relative;
        direction: rtl;
        text-align: right;
    }

    .verify-code {
        text-align: center;
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 20px;
    }

    .otp-description {
        text-align: center;
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .otp-mobile {
        text-align: center;
        color: #BF9456;
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 20px;
        direction: ltr;
    }

    .otp-form {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .otp-inputs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-direction: row-reverse;
    }

    .otp-box {
        width: 60px;
        height: 60px;
        text-align: center;
        font-size: 24px;
        border: 1px solid #ddd;
        border-radius: 12px;
        outline: none;
        background: #fff;
        font-weight: bold;
    }

    .otp-box:focus {
        border-color: #BF9456;
        box-shadow: 0 0 5px rgba(191, 148, 86, 0.5);
    }

    .send-btn {
        background: #BF9456;
        color: #fff;
        border: none;
        border-radius: 36px;
        padding: 15px 0;
        width: 100%;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .send-btn:hover {
        transform: translateY(-2px);
    }

    .resend-otp {
        text-align: center;
        margin-top: 20px;
        font-size: 14px;
        color: #666;
    }

    .resend-otp form {
        display: inline;
    }

    .resend-otp button {
        background: none;
        border: none;
        color: #BF9456;
        font-weight: 500;
        cursor: pointer;
    }

    .otp-valid-time {
        text-align: center;
        color: #999;
        font-size: 12px;
        margin-top: 10px;
    }
</style>

@include('components.frontend.notifications')

<div class="main-otp-box">
    <div class="sub-otp-box">
        <h2 class="verify-code">{{ __('auth.verify_code') }}</h2>
        <p class="otp-description">{{ __('auth.enter_otp_desc') }}</p>
        <p class="otp-mobile">{{ $mobile }}</p>

        <form method="POST" action="{{ route($submitRoute) }}" class="otp-form">
            @csrf

            <div class="otp-inputs">
                <input type="text" name="otp1" maxlength="1" inputmode="numeric" autocomplete="one-time-code" class="otp-box" required>
                <input type="text" name="otp2" maxlength="1" inputmode="numeric" autocomplete="one-time-code" class="otp-box" required>
                <input type="text" name="otp3" maxlength="1" inputmode="numeric" autocomplete="one-time-code" class="otp-box" required>
                <input type="text" name="otp4" maxlength="1" inputmode="numeric" autocomplete="one-time-code" class="otp-box" required>
            </div>

            <button type="submit" class="send-btn">{{ __('auth.send_button') }}</button>
        </form>

        @if ($resendRoute)
            <div class="resend-otp">
                <span>{{ __('auth.resend_otp') }}؟</span>
                <form method="POST" action="{{ route($resendRoute) }}">
                    @csrf
                    <button type="submit">{{ __('auth.resend_otp') }}</button>
                </form>
            </div>
        @endif

        <p class="otp-valid-time">{{ __('auth.otp_valid_time') }}</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const otpInputs = document.querySelectorAll('.otp-box');

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);

                if (input.value && otpInputs[index + 1]) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && input.value === '' && otpInputs[index - 1]) {
                    otpInputs[index - 1].focus();
                }
            });
        });
    });
</script>
@endsection

@if ($errors->any())
    @foreach ($errors->all() as $error)
        <script>
            createNotify({ title: 'خطأ', desc: '{{ $error }}' });
        </script>
    @endforeach
@endif

@if (session('success'))
    <script>
        createNotify({ title: 'نجاح', desc: '{{ session('success') }}' });
    </script>
@endif
