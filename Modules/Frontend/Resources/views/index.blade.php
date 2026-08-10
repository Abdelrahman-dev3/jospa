@extends('frontend::layouts.master')

@section('content')
    <x-frontend.learn-about-section />
    <x-frontend.services-section :services="$services" :categories="$categories" />
    <x-frontend.gift-section />
    <x-frontend.premium-packages-section :packages="$packages" />
    <x-frontend.product-section :products="$products" />
    <x-frontend.goals-vision-section />
@endsection
