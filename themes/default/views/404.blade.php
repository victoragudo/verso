{{-- Shown when a page does not exist or is not published. --}}
@extends('theme::layouts.app')

@section('title', 'Page not found')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <header class="page-header">
        <p class="eyebrow">Error 404</p>
        <h1 class="page-title">This page could not be found</h1>
        <p class="page-intro">It may have moved, or it was never published. Try a search, or go back to the <a href="{{ url('/') }}">home page</a>.</p>
    </header>

    @include('theme::partials.search-form', ['search' => '', 'inputId' => 'not-found-search'])
@endsection
