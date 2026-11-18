{{-- Shown when a page does not exist or is not published. --}}
@extends('theme::layouts.app')

@section('title', 'Page not found')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <header class="page-header page-header--center wrap">
        <p class="not-found-code" aria-hidden="true">404</p>
        <h1 class="page-title">This page is not here</h1>
        <p class="page-intro">It may have moved, or it was never published. Search for it, or start again from the <a href="{{ url('/') }}">home page</a>.</p>
        @include('theme::partials.search-form', ['search' => '', 'inputId' => 'not-found-search'])
    </header>
@endsection
