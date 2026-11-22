{{-- Newer and older links for a list of entries. --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('default::Pagination') }}">
        @if ($paginator->onFirstPage())
            <span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('default::← Newer') }}</a>
        @endif
        <span>{{ __('default::Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('default::Older →') }}</a>
        @else
            <span></span>
        @endif
    </nav>
@endif
