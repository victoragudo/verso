{{-- Home page of Saltmarsh: the newest stories only. --}}
@extends('theme::layouts.app')

@section('content')
    <h1 class="page-title">{{ $site['name'] }}</h1>
    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
