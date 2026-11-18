{{-- Home page with a featured story strip. --}}
@extends('theme::layouts.app')

@section('content')
    <h1>{{ $site['name'] }}</h1>
    <section class="featured">
        <h2>{{ $featuredStory->title }}</h2>
    </section>
    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
