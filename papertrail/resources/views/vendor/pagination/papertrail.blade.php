@if ($paginator->total() > 0)
    <nav class="pt-pagination" role="navigation" aria-label="Pagination navigation">
        <p class="pt-pagination-summary" aria-live="polite">
            <span>Showing</span>
            <strong>{{ number_format($paginator->firstItem()) }}&ndash;{{ number_format($paginator->lastItem()) }}</strong>
            <span>of</span>
            <strong>{{ number_format($paginator->total()) }}</strong>
            <span>{{ \Illuminate\Support\Str::plural('result', $paginator->total()) }}</span>
        </p>

        @if ($paginator->hasPages())
            <div class="pt-pagination-links">
                @if ($paginator->onFirstPage())
                    <span class="page-link page-link--edge disabled" aria-disabled="true">Previous</span>
                @else
                    <a class="page-link page-link--edge" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">Previous</a>
                @endif

                @foreach (($elements ?? []) as $element)
                    @if (is_string($element))
                        <span class="page-link page-link--ellipsis" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span class="page-link active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-link" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="page-link page-link--edge" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">Next</a>
                @else
                    <span class="page-link page-link--edge disabled" aria-disabled="true">Next</span>
                @endif
            </div>
        @endif
    </nav>
@endif
