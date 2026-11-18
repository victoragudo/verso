@extends('theme::layouts.app')

@section('title', $contentType->pluralLabel)

@section('content')
    <div class="wrap">
        <h1>{{ $contentType->pluralLabel }}</h1>
        @include('theme::partials.entry-list', ['entries' => $entries])
    </div>
@endsection
