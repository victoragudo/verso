{{-- One story. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    <article>
        <h1>{{ $entry->title }}</h1>
        <div class="prose">{!! $content !!}</div>
    </article>
@endsection
