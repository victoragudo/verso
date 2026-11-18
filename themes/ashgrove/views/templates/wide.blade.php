{{-- Wide page template: like a normal page, but the text uses the whole layout width. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    @include('theme::partials.page-body', ['entry' => $entry, 'content' => $content, 'wide' => true])
@endsection
