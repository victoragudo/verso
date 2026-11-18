{{-- One entry in a list: featured image, title, meta and excerpt. --}}
<li class="entry-card">
    <article>
        <x-verso-featured-image :entry="$entry" :eager="$eager ?? false" />
        <h2 style="view-transition-name: entry-title-{{ $entry->id }}"><a href="{{ $entry->url() }}">{{ $entry->title }}</a></h2>
        @include('theme::partials.entry-meta', ['entry' => $entry])
        @if ($entry->excerpt)
            <p>@shortcodes($entry->excerpt, 'excerpt', $entry)</p>
        @endif
    </article>
</li>
