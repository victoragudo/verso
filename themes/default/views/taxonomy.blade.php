{{-- Entries of one category, tag or other term. --}}
@extends('theme::layouts.app')

@section('title', $term->name)
@section('description', $term->description ?: $taxonomy->label.': '.$term->name)

@section('content')
    <header class="page-header">
        <p class="eyebrow">{{ $taxonomy->label }}</p>
        <h1 class="page-title">{{ $term->name }}</h1>
        @if ($term->description)
            <p class="page-intro">{{ $term->description }}</p>
        @endif
    </header>

    @include('theme::partials.entry-list', ['entries' => $entries, 'empty' => __('default::Nothing has been published in :term yet.', ['term' => $term->name])])
@endsection
