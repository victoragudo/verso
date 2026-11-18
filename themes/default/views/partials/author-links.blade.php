{{-- Social profile links of an author, from the profile page. Prints nothing without links. --}}
@php($profiles = $author->socialProfiles())
@if ($profiles !== [])
    <ul class="author-links" aria-label="{{ $author->name }} elsewhere">
        @foreach ($profiles as $profile)
            <li><a href="{{ $profile['url'] }}" rel="me noopener" target="_blank">{{ $profile['label'] }}</a></li>
        @endforeach
    </ul>
@endif
