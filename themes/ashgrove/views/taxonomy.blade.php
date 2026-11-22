{{-- Entries of one category, tag or other term. --}}
@extends('theme::layouts.app')

@section('title', $term->name)
@section('description', $term->description ?: $taxonomy->label.': '.$term->name)

@section('content')
    <header class="page-header wrap">
        <p class="page-kicker">{{ $taxonomy->label }}</p>
        <h1 class="page-title">{{ $term->name }}</h1>
        @if ($term->description)
            <p class="page-intro">{{ $term->description }}</p>
        @endif
    </header>

    <div class="wrap">
        @if ($taxonomy->name === 'category')
            @include('theme::partials.category-strip', ['term' => $term])
        @endif
        @include('theme::partials.post-grid', ['entries' => $entries, 'empty' => __('ashgrove::Nothing has been published in :term yet.', ['term' => $term->name])])
    </div>
@endsection
