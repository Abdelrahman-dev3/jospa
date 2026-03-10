@extends('layouts.frontend-page', ['showProgress' => true, 'topSpacerHeight' => '71.4px', 'bottomSpacerHeight' => '0vh'])

@section('head')
<!-- CSS -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>@yield('title') | {{ app_name() }}</title>
    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">

    @stack('after-styles')
    <link href="https://fonts.cdnfonts.com/css/lama-sans" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('pages-css/loyalety.css') }}">
@endsection

@section('content')
<!-- Page Content -->
            <section class="hero">
            <div class="hero-bg">
                <img src="{{ asset('loyality-bg.jpg') }}" alt="Background">
                <div class="hero-overlay"></div>
            </div>

        <div class="hero-content">
            <div class="hero-inner" style="padding-bottom:243px;">
                <div class="hero-heading">
                    <p>
                        {{ __('messages.loyalty_points_info') }}
                    </p>
                </div>

                <div class="pricing-card">
                    <div class="pricing-header">
                        <div class="pricing-header-item">{{ __('messages.points_number') }}</div>
                        <div class="pricing-header-item">{{ __('messages.amount') }}</div>
                    </div>

                    <div class="pricing-rows">
                        <div class="pricing-row">
                            <div class="pricing-cell">200 {{ __('messages.point') }}</div>
                            <div class="pricing-cell">{{ $point_value * 200 }} {{ __('messages.currency') }}</div>
                        </div>
                        <div class="pricing-row">
                            <div class="pricing-cell">400 {{ __('messages.point') }}</div>
                            <div class="pricing-cell">{{ $point_value * 400 }} {{ __('messages.currency') }}</div>
                        </div>
                        <div class="pricing-row">
                            <div class="pricing-cell">800 {{ __('messages.point') }}</div>
                            <div class="pricing-cell">{{ $point_value * 800 }} {{ __('messages.currency') }}</div>
                        </div>
                        <div class="pricing-row">
                            <div class="pricing-cell">1600 {{ __('messages.point') }}</div>
                            <div class="pricing-cell">{{ $point_value * 1600 }} {{ __('messages.currency') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Footer -->
@endsection
