@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    <article class="wrap entry">
        <h1>{{ $entry->title }}</h1>
        <x-verso-featured-image :entry="$entry" :eager="true" :skip-lead-image="true" />
        <div class="prose">
            {!! $content !!}
        </div>
    </article>

    <x-verso-comments :entry="$entry" />
@endsection
