{{-- One post: header, featured image with caption, contents list for long posts, author box, tags, neighbours, related posts and comments. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@push('head')
    {{-- Waits for the title before the first paint, so the page change can move it from the list. --}}
    <link rel="expect" href="#verso-entry-title" blocking="render">
@endpush

@section('content')
    @inject('blog', 'App\Verso\Theme\Support\BlogQueries')
    @inject('images', 'App\Verso\Media\ResponsiveImages')
    @php
        $category = $entry->termsIn('category')->first();
        $tags = $entry->termsIn('tag');
        $toc = App\Verso\Theme\Support\TableOfContents::from($content);
        $featuredId = $entry->meta['featured_image']['mediaId'] ?? null;
        $leadBlock = $entry->blocks[0] ?? null;
        $leadIsFeatured = is_array($leadBlock) && ($leadBlock['type'] ?? null) === 'image' && is_numeric($featuredId) && (int) ($leadBlock['attrs']['mediaId'] ?? 0) === (int) $featuredId;
        $featured = $leadIsFeatured ? null : $images->media($featuredId);
        ['previous' => $previous, 'next' => $next] = $blog->neighbours($entry);
        $postSettings = app(App\Verso\Theme\Customizer\ThemeCustomizer::class)->values(app(App\Verso\Theme\ThemeManager::class)->active());
        $related = $blog->related($entry, 'category', (int) config('verso.themes.related_posts'));
    @endphp

    <article class="post">
        <header class="post-header measure">
            @if ($category)
                <a class="post-category" href="{{ $category->url() }}">{{ $category->name }}</a>
            @endif
            <h1 class="post-title" id="verso-entry-title" style="view-transition-name: entry-title-{{ $entry->id }}">{{ $entry->title }}</h1>
            @if ($entry->excerpt)
                <p class="post-dek">{{ $entry->excerpt }}</p>
            @endif
            @include('theme::partials.post-meta', ['entry' => $entry, 'showAuthor' => true])
        </header>

        @if ($featured)
            <figure class="post-hero">
                <x-verso-featured-image :entry="$entry" class="post-hero-image" :eager="true" sizes="(min-width: 76rem) 72rem, 100vw" />
                @if ($featured->caption)
                    <figcaption>{{ $featured->caption }}</figcaption>
                @endif
            </figure>
        @endif

        @if ($toc->isUseful((int) config('verso.themes.toc_min_headings')))
            <nav class="toc measure" aria-labelledby="toc-title">
                <h2 id="toc-title" class="toc-title">In this post</h2>
                <ol>
                    @foreach ($toc->items as $item)
                        <li><a href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="prose measure">
            {!! $toc->html !!}
        </div>

        <footer class="post-footer measure">
            @if ($tags->isNotEmpty())
                <ul class="tags" aria-label="Tags">
                    @foreach ($tags as $tag)
                        <li><a href="{{ $tag->url() }}" rel="tag">{{ $tag->name }}</a></li>
                    @endforeach
                </ul>
            @endif

            @if (($postSettings['show_author_box'] ?? true) && $entry->author)
                @templatePart('author-box')
            @endif
        </footer>
    </article>

    @if ($previous || $next)
        <nav class="post-nav measure" aria-label="More posts">
            @if ($previous)
                <a class="post-nav-link post-nav-link--previous" href="{{ $previous->url() }}" rel="prev"><span class="post-nav-label">Previous post</span> {{ $previous->title }}</a>
            @endif
            @if ($next)
                <a class="post-nav-link post-nav-link--next" href="{{ $next->url() }}" rel="next"><span class="post-nav-label">Next post</span> {{ $next->title }}</a>
            @endif
        </nav>
    @endif

    <div class="measure comments-wrap">
        <x-verso-comments :entry="$entry" />
    </div>

    @if ($related->isNotEmpty())
        <section class="related wrap" aria-labelledby="related-title">
            <h2 id="related-title" class="section-title">More in {{ $category?->name }}</h2>
            <ol class="post-grid" role="list">
                @foreach ($related as $relatedEntry)
                    <li>@include('theme::partials.post-card', ['entry' => $relatedEntry])</li>
                @endforeach
            </ol>
        </section>
    @endif
@endsection
