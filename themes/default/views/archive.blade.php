{{-- All entries of one content type, like the blog. --}}
@extends('theme::layouts.app')

@section('title', $contentType->pluralLabel)

@section('content')
    <header class="page-header">
        <h1 class="page-title">{{ $contentType->pluralLabel }}</h1>
    </header>

    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection
