@extends('theme::layouts.app')

@section('content')
    <div class="wrap">
        <h1>{{ $site['name'] }}</h1>
        @include('theme::partials.entry-list', ['entries' => $entries])
    </div>
@endsection
