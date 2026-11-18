@extends('theme::layouts.app')

@section('title', __('lighthouse::Search'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="wrap">
        <h1>{{ $searching ? __('lighthouse::Results for “:search”', ['search' => $search]) : __('lighthouse::Search') }}</h1>
        <form role="search" method="get" action="{{ route('theme.search') }}">
            <label for="page-search">{{ __('lighthouse::Search the site') }}</label>
            <input id="page-search" type="search" name="q" value="{{ $search }}" required>
            <button type="submit">{{ __('lighthouse::Search') }}</button>
        </form>
        @if ($searching)
            @include('theme::partials.entry-list', ['entries' => $entries])
        @endif
    </div>
@endsection
