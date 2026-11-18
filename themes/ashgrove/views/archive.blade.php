{{-- All entries of one content type, like the blog. --}}
@extends('theme::layouts.app')

@section('title', $contentType->pluralLabel)

@section('content')
    <header class="page-header wrap">
        <h1 class="page-title">{{ $contentType->pluralLabel }}</h1>
    </header>

    <div class="wrap">
        @include('theme::partials.category-strip')
        @include('theme::partials.post-grid', ['entries' => $entries])
    </div>
@endsection
