@php
    $typeClasses = $typeClasses ?? [
        'Alam' => 'bg-green-700 text-white',
        'Budaya' => 'bg-amber-800 text-white',
        'Religi' => 'bg-gray-700 text-white',
        'Bahari' => 'bg-blue-700 text-white',
        'Kuliner' => 'bg-yellow-400 text-gray-900',
        'Buatan' => 'bg-purple-700 text-white',
    ];
@endphp

<div class="flex justify-between items-center text-[10px] font-bold text-gray-500 uppercase tracking-wider">
    <span>Destinations on the map</span>
    <span>{{ number_format($matchCount) }} found</span>
</div>

@forelse ($results as $destination)
    <article class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm hover:shadow-md hover:border-brand-primary/30 transition cursor-pointer" data-marker-id="{{ $destination->id }}" onclick="focusMarker(@js($destination->id))">
        <div class="flex gap-3 mb-3">
            <div class="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0">
                <img src="{{ $destination->imageUrl() }}" alt="{{ $destination->name }}" class="w-full h-full object-cover" loading="lazy">
                <span class="absolute bottom-1 left-1 {{ $typeClasses[$destination->primaryType()] ?? 'bg-brand-primary text-white' }} text-[8px] font-bold px-1.5 py-0.5 rounded">{{ $destination->primaryType() }}</span>
            </div>
            <div class="flex-grow min-w-0">
                <div class="flex justify-between items-start gap-2">
                    <h3 class="font-bold text-sm text-gray-900 truncate">{{ $destination->name }}</h3>
                    <span class="text-[10px] font-bold text-brand-primary whitespace-nowrap">⭐ {{ $destination->ratingLabel() }}</span>
                </div>
                <p class="text-[10px] text-gray-500 mb-1 truncate">{{ $destination->province() }} • {{ $destination->location }}</p>
                <p class="text-[10px] text-gray-500 truncate">{{ $destination->openingHours !== '' ? $destination->openingHours : 'Hours not recorded' }}</p>
                <p class="text-[10px] font-bold text-green-600 mt-1 truncate">{{ $destination->priceLabel() }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="flex-1 bg-brand-dark text-white text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-800">◈ Directions</a>
            <a href="{{ route('destination.detail', $destination->id) }}" onclick="event.stopPropagation()" class="flex-1 bg-brand-light border border-brand-primary/20 text-brand-dark text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-100">View Details</a>
        </div>
    </article>
@empty
    <div class="bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <p class="text-2xl mb-2">🔎</p>
        <p class="text-sm font-bold text-gray-900 mb-1">No destination matches</p>
        <p class="text-xs text-gray-500 mb-4">Try another keyword or category.</p>
        <a href="{{ route('map') }}" class="inline-block bg-brand-accent text-white text-xs font-bold px-4 py-2 rounded-full">Reset filters</a>
    </div>
@endforelse

@if ($matchCount > $results->count())
    <p class="text-center text-[10px] font-bold text-gray-400 py-3">
        Showing the first {{ $results->count() }} of {{ number_format($matchCount) }} matches. Use search or categories to narrow the list.
    </p>
@endif
