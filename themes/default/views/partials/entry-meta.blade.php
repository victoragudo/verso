{{-- Date and author of an entry. The date is shown in the site time zone. --}}
@inject('siteTime', 'App\Verso\Core\Support\SiteTime')
<p class="meta">
    @if ($entry->published_at)
        <time datetime="{{ $siteTime->iso($entry->published_at) }}">{{ $siteTime->format($entry->published_at, 'F j, Y') }}</time>
    @endif
    @if ($entry->author)
        <span>· by <a href="{{ $entry->author->authorUrl() }}" rel="author">{{ $entry->author->name }}</a></span>
    @endif
</p>
