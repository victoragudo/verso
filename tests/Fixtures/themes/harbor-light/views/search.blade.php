{{-- Search results. --}}
@extends('theme::layouts.app')

@section('title', 'Search')

@section('content')
    <h1>Search</h1>
    <form action="{{ url('/search') }}" method="get" role="search">
        <label for="search">Search the harbor log</label>
        <input id="search" type="search" name="q" value="{{ $search ?? '' }}">
    </form>
@endsection
