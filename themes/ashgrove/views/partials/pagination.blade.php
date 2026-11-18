{{-- Newer and older links for a list of entries. --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="pagination-gap"></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Newer posts</a>
        @endif
        <span class="pagination-status">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Older posts</a>
        @else
            <span class="pagination-gap"></span>
        @endif
    </nav>
@endif
