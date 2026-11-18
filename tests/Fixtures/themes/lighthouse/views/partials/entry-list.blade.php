@inject('siteTime', 'App\Verso\Core\Support\SiteTime')
@inject('customizer', 'App\Verso\Theme\Customizer\ThemeCustomizer')
@inject('themes', 'App\Verso\Theme\ThemeManager')
@php($settings = $customizer->values($themes->active()))
@if ($entries->isEmpty())
    <p>{{ __('lighthouse::Nothing has been published here yet.') }}</p>
@else
    <ol class="entry-list" role="list">
        @foreach ($entries as $entry)
            <li>
                <article>
                    <x-verso-featured-image :entry="$entry" :eager="$loop->first" sizes="(min-width: 48rem) 42rem, 100vw" />
                    <h2><a href="{{ $entry->url() }}">{{ $entry->title }}</a></h2>
                    @if ($entry->published_at)
                        <p class="meta"><time datetime="{{ $siteTime->iso($entry->published_at) }}">{{ $siteTime->format($entry->published_at, 'F j, Y') }}</time></p>
                    @endif
                    @if ($settings['show_excerpts'] && $entry->excerpt)
                        <p>{{ $entry->excerpt }}</p>
                    @endif
                </article>
            </li>
        @endforeach
    </ol>
    @if ($entries->hasPages())
        <nav class="pagination" aria-label="{{ __('lighthouse::Pagination') }}">
            @if (! $entries->onFirstPage())
                <a href="{{ $entries->previousPageUrl() }}" rel="prev">{{ __('lighthouse::Newer posts') }}</a>
            @endif
            @if ($entries->hasMorePages())
                <a href="{{ $entries->nextPageUrl() }}" rel="next">{{ __('lighthouse::Older posts') }}</a>
            @endif
        </nav>
    @endif
@endif
