{{-- Full width page template: like a normal page, but the text uses the whole layout width. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    @php($ancestors = $entry->ancestors())
    @php($children = $entry->children()->published()->get())

    <article class="entry entry--wide">
        <header class="entry-header">
            @if ($ancestors !== [])
                <nav class="breadcrumbs" aria-label="{{ __('default::Breadcrumb') }}">
                    <ol>
                        @foreach ($ancestors as $ancestor)
                            <li><a href="{{ $ancestor->url() }}">{{ $ancestor->title }}</a></li>
                        @endforeach
                        <li><span aria-current="page">{{ $entry->title }}</span></li>
                    </ol>
                </nav>
            @endif
            <h1 class="page-title">{{ $entry->title }}</h1>
        </header>

        <x-verso-featured-image :entry="$entry" class="entry-featured-image" :eager="true" :skip-lead-image="true" />

        <div class="prose">
            {!! $content !!}
        </div>

        @if ($children->isNotEmpty())
            <nav class="child-pages" aria-labelledby="child-pages-title">
                <h2 id="child-pages-title">{{ __('default::In this section') }}</h2>
                <ul>
                    @foreach ($children as $child)
                        <li><a href="{{ $child->url() }}">{{ $child->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </article>
@endsection
