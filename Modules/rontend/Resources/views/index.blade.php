@extends('frontend::layouts.master')

@section('content')

    <x-frontend.gift-section />
    <x-frontend.services-section :services="$services" :categories="$categories" />
    <x-frontend.premium-packages-section :packages="$packages" />
    <x-frontend.discount />
    <x-frontend.blog-section :blog-posts="$blogPosts" />
    <x-frontend.learn-about-section />
@endsection
