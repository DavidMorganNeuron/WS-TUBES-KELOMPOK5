<<<<<<< HEAD
@include('partials.search-notice')

@if ($destinations->isNotEmpty())
    <ul class="divide-y divide-gray-100" role="listbox" aria-label="Saran destinasi">
        @foreach ($destinations as $destination)
            <li role="presentation">
                    <a href="{{ route('destinations', ['q' => $destination->name]) }}" data-suggestion class="flex items-center gap-3 px-4 py-3 transition hover:bg-brand-light focus:bg-brand-light outline-none" role="option" aria-selected="false">
=======
@if ($destinations->isNotEmpty())
    <ul class="divide-y divide-gray-100">
        @foreach ($destinations as $destination)
            <li role="presentation">
                <a href="{{ route('destinations', ['q' => $destination->name]) }}" data-suggestion class="flex items-center gap-3 px-4 py-3 transition hover:bg-brand-light focus:bg-brand-light outline-none" role="option" aria-selected="false">
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                    <div class="relative w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100 skeleton-shimmer">
                        <img src="{{ $destination->imageUrl() }}" alt="" class="w-full h-full object-cover transition-opacity duration-300" loading="lazy" onload="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')" onerror="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')">
                        @include('partials.image-attribution', ['destination' => $destination])
                    </div>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-gray-900">{{ $destination->name }}</span>
                        <span class="block truncate text-[11px] text-gray-500">{{ $destination->province() }} • {{ $destination->location }}</span>
                    </span>
                    <span class="whitespace-nowrap text-[10px] font-bold text-brand-primary">⭐ {{ $destination->ratingLabel() }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2 text-[10px] font-bold text-gray-500">
<<<<<<< HEAD
        Tekan Enter untuk melihat semua destinasi yang sesuai
=======
        Press Enter to see all matching destinations
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    </div>
@endif
