{{-- Search form and results. The words of the search are marked in titles and excerpts. --}}
@extends('theme::layouts.app')

@section('title', $searching ? 'Search results for “'.$search.'”' : 'Search')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    @php($highlighter = new App\Verso\Theme\Support\SearchHighlighter((int) config('verso.themes.search_min_length')))
    <header class="page-header wrap">
        <h1 class="page-title">
            @if ($searching)
                Results for “{{ $search }}”
            @else
                Search
            @endif
        </h1>
        @if ($searching)
            <p class="page-intro" role="status">
                {{ trans_choice('{0} Nothing matched your search. Try fewer or different words.|{1} One result.|[2,*] :count results.', $entries->total(), ['count' => $entries->total()]) }}
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
                            <span class="post-category">Page</span>
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
