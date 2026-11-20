@if ($paginator->hasPages())
<nav class="pagination" aria-label="{{ __('Sayfalar') }}">
    @if ($paginator->onFirstPage())<span class="muted">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>@endif
    <span class="current">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>@else<span class="muted">›</span>@endif
</nav>
@endif
