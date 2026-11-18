{{-- Latest stories on the home page. --}}
@extends('theme::layouts.app')

@section('content')
    <h1>{{ $site['name'] }}</h1>
    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
