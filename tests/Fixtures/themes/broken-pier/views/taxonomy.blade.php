{{-- Stories with one category or tag. --}}
@extends('theme::layouts.app')

@section('content')
    <h1>{{ $term->name }}</h1>
    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
