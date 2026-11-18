@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    <article class="wrap entry entry--wide">
        <h1>{{ $entry->title }}</h1>
        <div class="prose">
            {!! $content !!}
        </div>
    </article>
@endsection
