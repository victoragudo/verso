{{-- Result page of the confirm and unsubscribe links, inside the active theme layout. Themes can override it. --}}
@extends('theme::layouts.app')

@section('title', $title)

@section('seo')
    <title>{{ $title }} - {{ $site['name'] ?? config('app.name') }}</title>
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <article class="newsletter-message">
        <header class="page-header">
            <h1 class="page-title">{{ $title }}</h1>
        </header>
        <p>{{ $message }}</p>
        <p><a href="{{ url('/') }}">{{ __('newsletter::Back to :site', ['site' => $site['name'] ?? config('app.name')]) }}</a></p>
    </article>
@endsection
