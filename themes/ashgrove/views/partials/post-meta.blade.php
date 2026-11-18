{{-- Date, reading time (when the Reading Time plugin is on) and author of an entry. Dates use the site time zone. --}}
@inject('siteTime', 'App\Verso\Core\Support\SiteTime')
@php($readingTime = app()->bound('Verso\Plugins\ReadingTime\ReadingTime') ? app('Verso\Plugins\ReadingTime\ReadingTime') : null)
<p class="post-meta">
    @if (($showAuthor ?? false) && $entry->author)
        <span class="post-author">{{ $entry->author->name }}</span>
    @endif
    @if ($entry->published_at)
        <time datetime="{{ $siteTime->iso($entry->published_at) }}">{{ $siteTime->format($entry->published_at, 'F j, Y') }}</time>
    @endif
    @if ($readingTime)
        <span class="reading-minutes">{{ $readingTime->text($readingTime->minutes($entry)) }}</span>
    @endif
</p>
