{{-- About the author of an entry: photo, name with a link to their page, bio and social links. Only shown with a bio. --}}
@php($author = $entry->author)
@if ($author && $author->bio)
    @php($avatar = $author->avatar_media_id ? app(App\Verso\Media\ResponsiveImages::class)->media($author->avatar_media_id) : null)
    <aside class="author-box" aria-labelledby="author-box-name">
        @if ($avatar && $avatar->isResizable())
            <img class="author-avatar" src="{{ $avatar->imageUrl(['w' => 128, 'h' => 128, 'fit' => 'crop']) }}" alt="" width="64" height="64" loading="lazy" decoding="async">
        @endif
        <div>
            <p id="author-box-name" class="author-name"><a href="{{ $author->authorUrl() }}" rel="author">{{ $author->name }}</a></p>
            <p class="author-bio">{{ $author->bio }}</p>
            @include('theme::partials.author-links', ['author' => $author])
        </div>
    </aside>
@endif
