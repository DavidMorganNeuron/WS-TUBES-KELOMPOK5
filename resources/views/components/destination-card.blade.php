@props([
    'destination',
    'badge' => null,
    'showDescription' => true,
])

@php
    $type = $destination->primaryType();
    $typeClasses = [
        'Alam' => 'bg-brand-primary',
        'Budaya' => 'bg-brand-accent',
        'Religi' => 'bg-emerald-800',
        'Bahari' => 'bg-sky-700',
        'Kuliner' => 'bg-amber-700',
        'Buatan' => 'bg-violet-800',
    ];
    $badgeClass = $typeClasses[$type] ?? 'bg-brand-primary';
    $detailUrl = route('destination.detail', $destination->id);
@endphp

<article class="bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 flex flex-col hover:shadow-md transition">
    <div class="relative h-56">
        <a href="{{ $detailUrl }}" class="block h-full">
            <img src="{{ $destination->imageUrl() }}" alt="{{ $destination->name }}" class="w-full h-full object-cover" loading="lazy">
        </a>
        <div class="absolute top-3 left-3 {{ $badgeClass }} text-white text-[10px] font-bold px-2 py-1 rounded">
            {{ $badge ?? $type }}
        </div>
        <button type="button" onclick="alert('Save to itinerary will be available in a future release.')" class="absolute top-3 right-3 bg-white/80 p-1.5 rounded-full text-gray-600 hover:text-red-500" aria-label="Save {{ $destination->name }}">
            ♡
        </button>
        <div class="absolute bottom-3 left-3 right-3 text-white text-[10px] font-bold bg-black/50 px-2 py-1 rounded truncate">
            {{ $destination->province() }} • {{ $destination->location !== '' ? $destination->location : 'Location pending' }}
        </div>
    </div>

    <div class="p-5 flex-grow flex flex-col">
        <div class="flex justify-between items-center gap-2 mb-1">
            <div class="text-xs font-bold text-brand-primary flex items-center gap-1">
                ⭐ {{ $destination->ratingLabel() }}
                @if ($destination->rating !== null)
                    <span class="font-normal text-gray-400">/ 5</span>
                @endif
            </div>
            <div class="text-[10px] font-bold text-gray-500 flex items-center gap-1">
                {{ implode(' • ', $destination->types) }}
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
            <a href="{{ $detailUrl }}" class="bg-brand-light text-brand-dark text-xs font-bold px-4 py-2 rounded-full hover:bg-gray-100 transition whitespace-nowrap">
                View Details →
            </a>
        </div>
    </div>
</article>
