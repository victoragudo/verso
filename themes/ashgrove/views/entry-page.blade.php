{{-- One page, with its parents as breadcrumbs and links to its child pages. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    @include('theme::partials.page-body', ['entry' => $entry, 'content' => $content])
@endsection
