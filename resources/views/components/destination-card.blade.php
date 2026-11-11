@props([
    'destination',
    'showDescription' => true,
])

@php
    $types = array_slice($destination->types, 0, 3);

    if ($types === []) {
        $types = [$destination->primaryType()];
    }

    $typeClasses = [
        'Alam' => 'bg-green-700 text-white',
        'Budaya' => 'bg-amber-800 text-white',
        'Religi' => 'bg-gray-700 text-white',
        'Bahari' => 'bg-blue-700 text-white',
        'Kuliner' => 'bg-yellow-400 text-gray-900',
        'Buatan' => 'bg-purple-700 text-white',
    ];
    $detailUrl = route('destination.detail', $destination->id);
@endphp

<article class="bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 flex flex-col hover:shadow-md transition">
    <div class="relative h-56 bg-gray-100 skeleton-shimmer overflow-hidden">
        <a href="{{ $detailUrl }}" class="block h-full">
            <img src="{{ $destination->imageUrl() }}" alt="{{ $destination->name }}" class="w-full h-full object-cover transition-opacity duration-300" loading="lazy" onload="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')" onerror="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')">
        </a>
        @include('partials.image-attribution', ['destination' => $destination])
        <div class="absolute top-3 left-3 right-3 flex flex-wrap gap-1">
            @foreach ($types as $type)
                <span class="{{ $typeClasses[$type] ?? 'bg-brand-primary text-white' }} text-[10px] font-bold px-2 py-1 rounded">
                    {{ $type }}
                </span>
            @endforeach
        </div>
        <div class="absolute bottom-3 left-3 right-3 text-white text-[10px] font-bold bg-black/50 px-2 py-1 rounded truncate">
            {{ $destination->province() }} • {{ $destination->location !== '' ? $destination->location : 'Location pending' }}
        </div>
    </div>

    <div class="p-5 flex-grow flex flex-col">
        <div class="flex items-center gap-2 mb-1">
            <div class="text-xs font-bold text-brand-primary flex items-center gap-1">
                ⭐ {{ $destination->ratingLabel() }}
                @if ($destination->rating !== null)
                    <span class="font-normal text-gray-400">/ 5</span>
                @endif
            </div>
        </div>

        <h3 class="font-bold text-lg text-gray-900 mb-2">
            <a href="{{ $detailUrl }}" class="hover:text-brand-primary transition">{{ $destination->name }}</a>
        </h3>

        @if ($showDescription)
            <p class="text-xs text-gray-500 mb-4 line-clamp-2">
                {{ $destination->shortDescription !== '' ? $destination->shortDescription : 'Description is not available yet.' }}
            </p>
        @endif

        <div class="flex flex-col gap-1 text-[11px] text-gray-500 mb-4">
            <span class="flex items-center gap-1">📍 {{ $destination->location !== '' ? $destination->location : 'Location pending' }}</span>
            @if ($destination->openingHours !== '')
                <span class="flex items-center gap-1">🕒 {{ $destination->openingHours }}</span>
            @endif
        </div>

        <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-auto gap-2">
            <div class="min-w-0">
                <p class="text-[10px] text-gray-400">Ticket Fee</p>
                <p class="font-bold text-sm text-gray-900 truncate">{{ $destination->priceLabel() }}</p>
            </div>
            <a href="{{ $detailUrl }}" class="destination-details-link bg-brand-light text-brand-dark text-xs font-bold px-4 py-2 rounded-full hover:bg-gray-100 transition whitespace-nowrap">
                View Details
            </a>
        </div>
    </div>
</article>
