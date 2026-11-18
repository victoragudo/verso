{{-- One post in a list: image, category, title, excerpt and meta. The "lead" variant is the large first story of the home page. --}}
@php
    $lead = ($variant ?? 'card') === 'lead';
    $category = $entry->relationLoaded('terms') ? $entry->termsIn('category')->first() : null;
@endphp
<article @class(['post-card', 'post-card--lead' => $lead])>
    <a class="post-card-media" href="{{ $entry->url() }}" tabindex="-1" aria-hidden="true">
        <x-verso-featured-image :entry="$entry" class="post-card-image" :eager="$eager ?? false" :sizes="$lead ? '(min-width: 64rem) 44rem, 100vw' : '(min-width: 64rem) 24rem, (min-width: 40rem) 50vw, 100vw'" />
    </a>
    <div class="post-card-body">
        @if ($category)
            <a class="post-category" href="{{ $category->url() }}">{{ $category->name }}</a>
        @endif
        <h2 class="post-card-title" style="view-transition-name: entry-title-{{ $entry->id }}"><a href="{{ $entry->url() }}">{{ $entry->title }}</a></h2>
        @if ($entry->excerpt)
            <p class="post-card-excerpt">{{ $entry->excerpt }}</p>
        @endif
        @include('theme::partials.post-meta', ['entry' => $entry, 'showAuthor' => $lead])
    </div>
</article>
