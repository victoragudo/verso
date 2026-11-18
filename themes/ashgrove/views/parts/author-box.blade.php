{{-- About the author of a post: photo, name with a link to their page, short bio and social links. --}}
@php($author = $entry->author)
@php($avatar = $author->avatar_media_id ? app(App\Verso\Media\ResponsiveImages::class)->media($author->avatar_media_id) : null)
<aside class="author-box" aria-labelledby="author-box-name">
    @if ($avatar && $avatar->isResizable())
        <img class="author-avatar" src="{{ $avatar->imageUrl(['w' => 160, 'h' => 160, 'fit' => 'crop']) }}" alt="" width="80" height="80" loading="lazy" decoding="async">
    @else
        <span class="author-avatar author-avatar--initials" aria-hidden="true">{{ mb_substr($author->name, 0, 1) }}</span>
    @endif
    <div>
        <p class="author-label">Written by</p>
        <p id="author-box-name" class="author-name"><a href="{{ $author->authorUrl() }}" rel="author">{{ $author->name }}</a></p>
        @if ($author->bio)
            <p class="author-bio">{{ $author->bio }}</p>
        @endif
        @include('theme::partials.author-links', ['author' => $author])
    </div>
</aside>
