{{-- One entry, like a blog post. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@push('head')
    {{-- Waits for the title before the first paint, so the page change can move it from the list. --}}
    <link rel="expect" href="#verso-entry-title" blocking="render">
@endpush

@section('content')
    @php($categories = $entry->termsIn('category'))
    @php($tags = $entry->termsIn('tag'))

    <article class="entry">
        <header class="entry-header">
            @if ($categories->isNotEmpty())
                <p class="eyebrow">
                    @foreach ($categories as $category)
                        <a href="{{ $category->url() }}">{{ $category->name }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif
            <h1 class="page-title" id="verso-entry-title" style="view-transition-name: entry-title-{{ $entry->id }}">{{ $entry->title }}</h1>
            @include('theme::partials.entry-meta', ['entry' => $entry])
        </header>

        <x-verso-featured-image :entry="$entry" class="entry-featured-image" :eager="true" :skip-lead-image="true" />

        <div class="prose">
            {!! $content !!}
        </div>

        @if ($tags->isNotEmpty())
            <ul class="terms" aria-label="{{ __('default::Tags') }}">
                @foreach ($tags as $tag)
                    <li><a href="{{ $tag->url() }}" rel="tag">#{{ $tag->name }}</a></li>
                @endforeach
            </ul>
        @endif

        @include('theme::partials.author-box', ['entry' => $entry])
    </article>

    <x-verso-comments :entry="$entry" />
@endsection
