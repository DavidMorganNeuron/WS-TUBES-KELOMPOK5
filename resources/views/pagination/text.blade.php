@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="destination-pagination flex flex-wrap items-center justify-end gap-1">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-400">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">Previous</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-500">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">Next</a>
        @else
            <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-400">Next</span>
        @endif
    </nav>
@endif
