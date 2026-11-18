{{-- A page drawn with a theme builder template inside the layout of the active theme. --}}
@extends('theme::layouts.app')

@if (isset($entry) && $entry instanceof App\Verso\Content\Models\Entry)
    @section('seo')
        <x-verso-seo :entry="$entry" />
    @endsection
@endif

@isset($layoutTitle)
    @section('title', $layoutTitle)
@endisset

@section('content')
    {!! $layoutHtml !!}
@endsection
