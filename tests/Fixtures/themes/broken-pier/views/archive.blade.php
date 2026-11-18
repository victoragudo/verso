{{-- All stories of one type. --}}
@extends('theme::layouts.app')

@section('title', $contentType->pluralLabel)

@section('content')
    <h1>{{ $contentType->pluralLabel }}</h1>
    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
