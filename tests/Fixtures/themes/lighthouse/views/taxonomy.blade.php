@extends('theme::layouts.app')

@section('title', $term->name)

@section('content')
    <div class="wrap">
        <p class="meta">{{ $taxonomy->label }}</p>
        <h1>{{ $term->name }}</h1>
        @include('theme::partials.entry-list', ['entries' => $entries])
    </div>
@endsection
