{{-- Date and author of an entry. The date is shown in the site time zone. --}}
@inject('siteTime', 'App\Verso\Core\Support\SiteTime')
<p class="meta">
    @if ($entry->published_at)
        <time datetime="{{ $siteTime->iso($entry->published_at) }}">{{ $siteTime->date($entry->published_at) }}</time>
    @endif
    @if ($entry->author)
        <span>· {!! __('default::by :author', ['author' => '<a href="'.e($entry->author->authorUrl()).'" rel="author">'.e($entry->author->name).'</a>']) !!}</span>
    @endif
</p>
