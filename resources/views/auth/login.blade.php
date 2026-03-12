@extends('frontend::layouts.auth') 

@section('title', __('auth.login'))

@section('content')
<div style="
    display:flex;
    justify-content:center;
    align-items:flex-start;
    min-height:calc(100vh - 100px);
    padding:0 20px 40px 20px;
    direction:{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }};
    text-align:{{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
    font-family: 'Almarai', sans-serif important;
    background-color:white;
    margin:0;
    padding-top:100px;
    padding-bottom:40px;

">
    <div style="
        background:#F9F6F0;
        padding:40px 30px;
        width:100%;
        max-width: 556px;
        height: 404px;
        position:relative;
        z-index:10;
    ">
        <h2 style="text-align:center;margin-bottom:20px;font-size:24px;font-weight:bold;">{{ __('auth.login') }}</h2>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('signin.verify') }}" style="display:flex;flex-direction:column;">
            @csrf
            <input type="hidden" name="redirect" value="{{ request('redirect') }}">

            <!-- Mobile -->
            <div style="margin-bottom:15px;">
                <label for="mobile" style="display:block;margin-bottom:6px;font-weight:500;font-size:14px;">{{ __('auth.mobile') }}</label>
                <input id="mobile" type="text" name="mobile"
                    value="{{ old('mobile', '+966') }}" required inputmode="tel" dir="ltr"
                    placeholder="+9665XXXXXXXX"
                    style="
                        width:100%;
                        border:1px solid #ddd;
                        border-radius:6px;
                        padding:10px 14px;
                        font-size:14px;
                        outline:none;
                        box-sizing:border-box;
                        font-style:italic !important;
                        transition:border-color 0.3s,box-shadow 0.3s;
                        text-align:left;
                    ">
            </div>

            <!-- Remember Me -->
            <!--<div style="display:flex;flex-direction:{{ app()->getLocale() == 'ar' ? 'row-reverse' : 'row' }};align-items:center;margin-bottom:15px;">-->
            <!--    <input id="remember_me" type="checkbox" name="remember" style="width:16px;height:16px;margin-right:6px;">-->
            <!--    <label for="remember_me" style="margin:0;font-size:14px;">{{ __('auth.remember_me') }}</label>-->
            <!--</div>-->

            <!-- Submit -->
            <div style="margin-top:15px;display: flex;">
                <button type="submit" style="background: rgb(191, 148, 86);font-weight: bold;border: none;border-radius: 36px;padding: 15px 20px;width: 50%;margin: auto;font-size: 18px;cursor: pointer;transition: background 0.3s, transform 0.2s;display: inline-block;text-align: center;color: rgb(255, 255, 255) !important;transform: none;" onmouseover="this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.transform='none';">
                    {{ __('auth.login_button') }}
                </button>
            </div>
        </form>

        <p style="text-align:center;margin-top:20px;">
            {{ __('auth.no_account') }}
            <a href="{{ route('signup') }}" style="color:#BF9456;font-weight:500;text-decoration:none;">{{ __('auth.signup') }}</a>
        </p>
    </div>
</div>

<!-- Toastr JS & CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "positionClass": "toast-top-center",
    "timeOut": "7000",
    "extendedTimeOut": "1000"
};

@if (session('message'))
    toastr.success("{{ session('message') }}");
@endif

if (localStorage.getItem('flash_message')) {
    toastr.success(localStorage.getItem('flash_message'));
    localStorage.removeItem('flash_message');
}
</script>6
@if(session('error'))
<script>
    toastr.error("{{ session('error') }}");
</script>
@endif

@if(session('success'))
<script>
    toastr.success("{{ session('success') }}");
</script>
@endif

@endsection
