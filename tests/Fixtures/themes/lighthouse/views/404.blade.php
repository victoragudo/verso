@extends('theme::layouts.app')

@section('title', __('lighthouse::Page not found'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="wrap">
        <h1>{{ __('lighthouse::Page not found') }}</h1>
        <p><a href="{{ url('/') }}">{{ __('lighthouse::Go to the home page') }}</a></p>
    </div>
@endsection
