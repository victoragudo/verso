{{-- A page: breadcrumbs, title, featured image, text and links to its child pages. "wide" lets the text use the layout width. --}}
@php($ancestors = $entry->ancestors())
@php($children = $entry->children()->published()->orderBy('menu_order')->orderBy('title')->get())
<article @class(['page', 'page--wide' => $wide ?? false])>
    <header class="post-header measure">
        @if ($ancestors !== [])
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <ol>
                    @foreach ($ancestors as $ancestor)
                        <li><a href="{{ $ancestor->url() }}">{{ $ancestor->title }}</a></li>
                    @endforeach
                    <li><span aria-current="page">{{ $entry->title }}</span></li>
                </ol>
            </nav>
        @endif
        <h1 class="post-title">{{ $entry->title }}</h1>
        @if ($entry->excerpt)
            <p class="post-dek">{{ $entry->excerpt }}</p>
        @endif
    </header>

    <x-verso-featured-image :entry="$entry" class="post-hero-image page-hero" :eager="true" :skip-lead-image="true" sizes="(min-width: 76rem) 72rem, 100vw" />

    <div @class(['prose', 'measure' => ! ($wide ?? false), 'wrap' => $wide ?? false])>
        {!! $content !!}
    </div>

    @if ($children->isNotEmpty())
        <nav class="child-pages measure" aria-labelledby="child-pages-title">
            <h2 id="child-pages-title" class="section-title">In this section</h2>
            <ul>
                @foreach ($children as $child)
                    <li><a href="{{ $child->url() }}">{{ $child->title }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
</article>
