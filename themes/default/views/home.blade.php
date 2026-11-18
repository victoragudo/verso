{{-- Home page with the latest entries. --}}
@extends('theme::layouts.app')

@section('content')
    <header class="page-header">
        <h1 class="page-title">{{ $site['name'] }}</h1>
        @if ($site['tagline'] !== '')
            <p class="page-intro">{{ $site['tagline'] }}</p>
        @endif
    </header>

    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
