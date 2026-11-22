@if ($paginator->hasPages())
<<<<<<< HEAD
    <nav aria-label="Navigasi halaman destinasi" class="destination-pagination flex flex-wrap items-center justify-end gap-1">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-400">Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">Sebelumnya</a>
=======
    <nav role="navigation" aria-label="Pagination Navigation" class="destination-pagination flex flex-wrap items-center justify-end gap-1">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-400">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">Previous</a>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-500">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
<<<<<<< HEAD
                        <span aria-current="page" class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white">{{ \App\Support\IndonesianFormat::number($page) }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="Buka halaman {{ \App\Support\IndonesianFormat::number($page) }}" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">{{ \App\Support\IndonesianFormat::number($page) }}</a>
=======
                        <span aria-current="page" class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">{{ $page }}</a>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
<<<<<<< HEAD
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">Berikutnya</a>
        @else
            <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-400">Berikutnya</span>
=======
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-primary">Next</a>
        @else
            <span aria-disabled="true" class="rounded-md px-3 py-2 text-sm text-gray-400">Next</span>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        @endif
    </nav>
@endif
