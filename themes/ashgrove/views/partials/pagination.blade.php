{{-- Newer and older links for a list of entries. --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('ashgrove::Pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="pagination-gap"></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('ashgrove::Newer posts') }}</a>
        @endif
        <span class="pagination-status">{{ __('ashgrove::Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('ashgrove::Older posts') }}</a>
        @else
            <span class="pagination-gap"></span>
        @endif
    </nav>
@endif
