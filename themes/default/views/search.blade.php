{{-- Search form and results. --}}
@extends('theme::layouts.app')

@section('title', $searching ? 'Search results for “'.$search.'”' : 'Search')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <header class="page-header">
        <h1 class="page-title">
            @if ($searching)
                Results for “{{ $search }}”
            @else
                Search
            @endif
        </h1>
        @if ($searching)
            <p class="page-intro" role="status">
                {{ trans_choice('{0} No entries found.|{1} One entry found.|[2,*] :count entries found.', $entries->total(), ['count' => $entries->total()]) }}
            </p>
        @endif
    </header>

    @include('theme::partials.search-form', ['search' => $search, 'inputId' => 'page-search'])

    @if ($searching && $entries->isNotEmpty())
        <div class="results">
            @include('theme::partials.entry-list', ['entries' => $entries])
        </div>
    @endif
@endsection
