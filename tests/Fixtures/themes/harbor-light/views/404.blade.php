{{-- Page not found. --}}
@extends('theme::layouts.app')

@section('title', 'Page not found')

@section('content')
    <h1>This page drifted away</h1>
    <p><a href="{{ url('/') }}">Back to the harbor</a></p>
@endsection
