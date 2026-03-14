@extends('frontend::layouts.auth')

@section('title', __('auth.singup'))

@section('content')

<style>
    body {
        font-family: 'Zain', sans-serif !important;
    }
</style>
<div style="
    display:flex;
    justify-content:center;
    align-items:flex-start;
    min-height:calc(100vh - 100px);
    padding:0 20px 40px 20px;
    direction:{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }};
    text-align:{{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
    font-family: 'Zain', sans-serif important;
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
        <h2 style="text-align:center;margin-bottom:20px;font-size:24px;font-weight:bold;font-family: 'Almarai';">{{ __('auth.singup') }}</h2>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('signup.store') }}" style="display:flex;flex-direction:column;">
            @csrf
            <input type="hidden" name="redirect" value="{{ request('redirect') }}">

            <!-- Name -->
            <div style="margin-bottom:15px;">
                <label for="username" style="display:block;margin-bottom:6px;font-weight:500;font-size:14px;">{{ __('auth.name') }}</label>
                <input id="username" type="text" name="username"
                    value="{{ old('username') }}" required autofocus
                    placeholder="{{ __('auth.enter_name') }}"
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
                        text-align:{{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
                    ">
            </div>

            <!-- Mobile -->
            <div style="margin-bottom:15px;">
                <label for="mobile" style="display:block;margin-bottom:6px;font-weight:500;font-size:14px;">{{ __('auth.mobile') }}</label>
                <input id="mobile" type="text" name="mobile"
                    value="{{ old('mobile') }}" required inputmode="tel" dir="ltr"
                    placeholder="05XXXXXXXX"
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
                <button type="submit" style="
                    background:#BF9456;
                    color:#fff !important;
                    font-weight:bold;
                    border:none;
                    font-family: Almarai;
                    border-radius:36px;
                    width:50%;
                    margin: auto;
                    cursor:pointer;
                    transition:background 0.3s ease,transform 0.2s ease;
                    display:inline-block;
                    text-align:center;
                    padding: 15px 20px;
                    font-size: 18px;
                " onmouseover="this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.transform='none';">
                    {{ __('auth.singup_button') }}
                </button>
            </div>
        </form>

      
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
</script>
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
