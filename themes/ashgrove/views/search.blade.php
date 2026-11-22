{{-- Search form and results. The words of the search are marked in titles and excerpts. --}}
@extends('theme::layouts.app')

@section('title', $searching ? __('ashgrove::Search results for “:search”', ['search' => $search]) : __('ashgrove::Search'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    @php($highlighter = new App\Verso\Theme\Support\SearchHighlighter((int) config('verso.themes.search_min_length')))
    <header class="page-header wrap">
        <h1 class="page-title">
            @if ($searching)
                {{ __('ashgrove::Results for “:search”', ['search' => $search]) }}
            @else
                {{ __('ashgrove::Search') }}
            @endif
        </h1>
        @if ($searching)
            <p class="page-intro" role="status">
                @php($resultCount = App\Verso\I18n\Translate::icu('ashgrove::{count, plural, =0 {Nothing matched your search. Try fewer or different words.} one {One result.} other {# results.}}', ['count' => $entries->total()]))
                {{ $resultCount }}
            </p>
        @endif
        @include('theme::partials.search-form', ['search' => $search, 'inputId' => 'page-search'])
    </header>

    @if ($searching && $entries->isNotEmpty())
        @php($entries->getCollection()->loadMissing('terms'))
        <div class="wrap">
            <ol class="search-results" role="list">
                @foreach ($entries as $entry)
                    <li class="search-result">
                        @php($category = $entry->termsIn('category')->first())
                        @if ($category)
                            <a class="post-category" href="{{ $category->url() }}">{{ $category->name }}</a>
                        @elseif ($entry->type === 'page')
                            <span class="post-category">{{ __('ashgrove::Page') }}</span>
                        @endif
                        <h2 class="search-result-title"><a href="{{ $entry->url() }}">{{ $highlighter->mark($entry->title, $search) }}</a></h2>
                        @if ($entry->excerpt)
                            <p class="search-result-excerpt">{{ $highlighter->mark($entry->excerpt, $search) }}</p>
                        @endif
                        @if ($entry->type !== 'page')
                            @include('theme::partials.post-meta', ['entry' => $entry])
                        @endif
                    </li>
                @endforeach
            </ol>
            @include('theme::partials.pagination', ['paginator' => $entries])
        </div>
    @endif
@endsection
