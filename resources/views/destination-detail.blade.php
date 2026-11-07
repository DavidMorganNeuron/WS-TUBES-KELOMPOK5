@extends('layouts.app')

@php
    $paragraphs = array_values(array_filter(array_map(
        'trim',
        preg_split('/\R{2,}/', trim($destination->fullDescription)) ?: [],
    )));
    $typeClasses = [
        'Alam' => 'bg-green-700 text-white',
        'Budaya' => 'bg-amber-800 text-white',
        'Religi' => 'bg-gray-700 text-white',
        'Bahari' => 'bg-blue-700 text-white',
        'Kuliner' => 'bg-yellow-400 text-gray-900',
        'Buatan' => 'bg-purple-700 text-white',
    ];
    $phoneHref = 'tel:'.preg_replace('/[^0-9+]/', '', $destination->phone);
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full overflow-x-clip">

    <!-- Breadcrumb -->
    <div class="text-[11px] text-gray-500 font-medium mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('home') }}" class="hover:text-brand-primary">🏠 Home</a> <span>›</span>
        <a href="{{ route('destinations') }}" class="hover:text-brand-primary">Destinations</a> <span>›</span>
        <a href="{{ route('destinations', ['provinsi' => $destination->province()]) }}" class="hover:text-brand-primary">{{ $destination->province() }}</a> <span>›</span>
        <span class="text-brand-primary font-bold">{{ $destination->name }}</span>
    </div>

    <!-- Header & Badges -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach ($destination->types as $type)
                <span class="{{ $typeClasses[$type] ?? 'bg-brand-primary text-white' }} text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1">
                    {{ $type }}
                </span>
            @endforeach
            <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1">⭐ {{ $destination->ratingLabel() }} / 5</span>
            @if ($destination->hasCoordinates())
                <span class="bg-[#e8f5e9] text-[#0f5132] text-[10px] font-bold px-3 py-1 rounded-full border border-[#0f5132]/20">📍 Geotagged</span>
            @endif
            @if ($destination->media !== '')
                <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1">📷 Ontology Media</span>
            @endif
        </div>

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6">
            <div class="min-w-0">
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-2 leading-tight">{{ $destination->name }}</h1>
                @if ($destination->alternativeName !== '' && $destination->alternativeName !== $destination->name)
                    <p class="text-lg text-gray-500 italic mb-2">{{ $destination->alternativeName }}</p>
                @endif
                <p class="text-gray-600 text-lg max-w-3xl">{{ $destination->shortDescription !== '' ? $destination->shortDescription : 'No short description has been recorded for this destination yet.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button type="button" onclick="shareDestination()" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm">↗ Share</button>
                <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm">📍 Google Maps</a>
            </div>
        </div>
    </div>

    <!-- Gallery -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10 md:h-[520px]">
        <div class="md:col-span-2 relative rounded-2xl overflow-hidden h-72 md:h-full min-h-0">
            <img src="{{ $destination->imageUrl() }}" class="absolute inset-0 w-full h-full object-cover" alt="{{ $destination->name }}">
            <div class="absolute bottom-0 left-0 w-full p-6 bg-gradient-to-t from-black/80 to-transparent text-white">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest mb-1">{{ implode(' • ', $destination->types) }}</p>
                    <p class="text-xl font-serif font-bold truncate">{{ $destination->location !== '' ? $destination->location : $destination->province() }}</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col md:h-full min-h-0">
            @if ($related->isNotEmpty())
                <div class="flex items-end justify-between gap-3 mb-3">
                    <div>
                        <p class="text-[10px] font-bold text-brand-accent uppercase tracking-widest">Recommendations</p>
                        <h2 class="text-lg font-serif font-bold text-gray-900 leading-tight">You may also like</h2>
                    </div>
                    <a href="{{ route('destinations', ['jenis' => $destination->primaryType()]) }}" class="text-[11px] font-bold text-brand-accent hover:underline whitespace-nowrap">See more →</a>
                </div>
            @endif

        <div class="grid grid-cols-3 gap-3 md:grid-cols-1 md:grid-rows-3 md:gap-4 flex-1 min-h-0">
            @foreach ($related->take(3) as $nearby)
                <a href="{{ route('destination.detail', $nearby->id) }}" class="relative rounded-2xl overflow-hidden h-28 md:h-auto min-h-0 group">
                    <img src="{{ $nearby->imageUrl() }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="{{ $nearby->name }}" loading="lazy">
                    <div class="absolute bottom-0 left-0 w-full p-3 bg-gradient-to-t from-black/80 to-transparent text-white">
                        <p class="text-[10px] font-bold truncate">{{ $nearby->name }}</p>
                        <p class="text-[9px] text-gray-300 truncate">{{ $nearby->province() }} • {{ $nearby->primaryType() }}</p>
                    </div>
                </a>
            @endforeach

            @for ($index = $related->take(3)->count(); $index < 3; $index++)
                <div class="relative rounded-2xl overflow-hidden h-28 md:h-auto min-h-0 bg-gray-200">
                    <img src="https://placehold.co/600x400/e5e9e2/0f5132?text={{ urlencode($destination->primaryType()) }}" class="absolute inset-0 w-full h-full object-cover" alt="Ontology media placeholder">
                    <div class="absolute bottom-0 left-0 w-full p-3 bg-gradient-to-t from-black/80 to-transparent text-white">
                        <p class="text-[10px] font-bold truncate">{{ $destination->name }}</p>
                        <p class="text-[9px] text-gray-300">Additional media not recorded</p>
                    </div>
                </div>
            @endfor
        </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        <!-- Sidebar (Visitor Snapshot paling atas) -->
        <aside class="space-y-6 lg:order-2 lg:sticky lg:top-24 min-w-0">
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                <div class="flex justify-between items-start mb-4 gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Visitor Snapshot</p>
                        <h3 class="text-xl font-serif font-bold text-gray-900 leading-snug">{{ $destination->name }}</h3>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-[10px] text-gray-400">Rating</p>
                        <p class="font-bold text-brand-primary">⭐ {{ $destination->ratingLabel() }}</p>
                    </div>
                </div>

                <div class="space-y-2 text-xs text-gray-600 mb-5">
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Category</span><span class="font-bold text-gray-900 text-right">{{ implode(', ', $destination->types) }}</span></div>
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Province</span><span class="font-bold text-gray-900 text-right">{{ $destination->province() }}</span></div>
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Opening hours</span><span class="font-bold text-gray-900 text-right">{{ $destination->openingHours !== '' ? $destination->openingHours : '—' }}</span></div>
                    <div class="flex justify-between gap-4"><span>Entry from</span><span class="font-bold text-gray-900 text-right">{{ $destination->priceLabel() }}</span></div>
                </div>

                <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" class="block text-center bg-brand-dark hover:bg-gray-800 text-white font-bold text-sm py-3 rounded-lg transition mb-3">
                    📍 Open in Google Maps
                </a>
                <a href="{{ route('destinations', ['jenis' => $destination->primaryType()]) }}" class="block text-center bg-brand-light border border-brand-primary/15 text-brand-dark font-bold text-sm py-3 rounded-lg hover:bg-gray-100 transition">
                    More {{ $destination->primaryType() }} destinations
                </a>

                <div class="flex justify-center text-[10px] text-gray-500 bg-gray-50 py-2 rounded-lg border border-gray-100 mt-4 text-center">
                    🛡️ Emergency assistance: National 112<br>Sumut Tourist Police (+62 611 0)
                </div>
            </div>
        </aside>

        <!-- Main Column -->
        <div class="lg:col-span-2 lg:order-1 space-y-6 min-w-0">

            <!-- About -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2 flex items-center gap-2">📖 Destination Story</p>
                <h2 class="text-3xl font-serif font-bold text-gray-900 mb-4">About {{ $destination->name }}</h2>

                <div class="text-sm text-gray-600 space-y-4 leading-relaxed">
                    @forelse ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p>{{ $destination->shortDescription !== '' ? $destination->shortDescription : 'The full description for this destination has not been recorded in the ontology yet.' }}</p>
                    @endforelse
                </div>

                @if ($destination->keywords !== [])
                    <div class="flex flex-wrap gap-2 mt-6 pt-6 border-t border-gray-100">
                        @foreach ($destination->keywords as $keyword)
                            <a href="{{ route('destinations', ['q' => $keyword]) }}" class="text-[10px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-3 py-1 hover:border-brand-primary hover:text-brand-primary transition">#{{ $keyword }}</a>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8 pt-6 border-t border-gray-100">
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Province</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->province() }}</p>
                        <p class="text-[10px] text-gray-500">Ontology region</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Category</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->primaryType() }}</p>
                        <p class="text-[10px] text-gray-500">{{ count($destination->types) }} recorded type(s)</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Coordinates</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->hasCoordinates() ? number_format($destination->latitude, 4).', '.number_format($destination->longitude, 4) : '—' }}</p>
                        <p class="text-[10px] text-gray-500">Latitude, longitude</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Rating</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->ratingLabel() }} / 5</p>
                        <p class="text-[10px] text-gray-500">Visitor average</p>
                    </div>
                </div>
            </div>

            <!-- Visitor Information -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-1">Visitor Information</h2>
                    <p class="text-xs text-gray-500">Operational hours, ticketing, and official contact channels.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                        <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">🕒 Operational Hours</p>
                        <p class="text-xs text-gray-700 font-bold mb-1">{{ $destination->openingHours !== '' ? $destination->openingHours : 'Not recorded' }}</p>
                        <p class="text-[10px] text-gray-500">Schedules may change during public holidays or extreme weather.</p>
                    </div>

                    <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                        <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">🎟️ Ticket Prices</p>
                        @if ($destination->priceLabels() !== [])
                            <ul class="space-y-1">
                                @foreach ($destination->priceLabels() as $price)
                                    <li class="text-xs text-gray-700 font-bold">{{ $price }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-xs text-gray-700 font-bold mb-1">No ticket information recorded.</p>
                        @endif
                        <p class="text-[10px] text-gray-500 mt-1">Fees are collected by the destination manager.</p>
                    </div>

                    <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                        <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">📞 Official Contact</p>
                        <div class="space-y-1 text-xs text-gray-700">
                            @if ($destination->phone !== '')
                                <p><a href="{{ $phoneHref }}" class="font-bold hover:text-brand-primary">{{ $destination->phone }}</a></p>
                            @endif
                            @if ($destination->email !== '')
                                <p><a href="mailto:{{ $destination->email }}" class="font-bold hover:text-brand-primary break-all">{{ $destination->email }}</a></p>
                            @endif
                            @if ($destination->instagram !== '')
                                <p><a href="{{ $destination->instagram }}" target="_blank" rel="noopener" class="font-bold hover:text-brand-primary break-all">Instagram profile ↗</a></p>
                            @endif
                            @if ($destination->phone === '' && $destination->email === '' && $destination->instagram === '')
                                <p class="font-bold">No official contact recorded.</p>
                            @endif
                        </div>
                    </div>

                    <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                        <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">📍 Address</p>
                        <p class="text-xs text-gray-700 font-bold mb-1">{{ $destination->address !== '' ? $destination->address : $destination->location }}</p>
                        <p class="text-[10px] text-gray-500">{{ $destination->location }}</p>
                    </div>
                </div>
            </div>

            <!-- Facilities & Activities -->
            @if ($destination->facilities !== [] || $destination->activities !== [])
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2 flex items-center gap-2">🌿 Facilities & Experiences</p>
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">What you can find and do</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-brand-light/60 p-6 rounded-2xl">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2 mb-4"><span class="w-2 h-2 rounded-full bg-brand-primary"></span> Available Facilities</h3>
                            @if ($destination->facilities !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($destination->facilities as $facility)
                                        <span class="text-xs bg-white border border-brand-primary/15 text-gray-700 rounded-full px-3 py-1">{{ $facility }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500">No facilities recorded.</p>
                            @endif
                        </div>

                        <div class="bg-orange-50/60 p-6 rounded-2xl">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2 mb-4"><span class="w-2 h-2 rounded-full bg-brand-accent"></span> Things To Do</h3>
                            @if ($destination->activities !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($destination->activities as $activity)
                                        <span class="text-xs bg-white border border-brand-accent/15 text-gray-700 rounded-full px-3 py-1">{{ $activity }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500">No activities recorded.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Location -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <div class="flex justify-between items-end gap-4 mb-4">
                    <div class="min-w-0">
                        <h2 class="text-2xl font-serif font-bold text-gray-900 mb-1">Location &amp; Access</h2>
                        <p class="text-xs text-gray-500">{{ $destination->address !== '' ? $destination->address : $destination->location }}</p>
                    </div>
                    <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" class="text-brand-accent hover:underline text-xs font-bold flex items-center gap-1 whitespace-nowrap">Open in Google Maps ↗</a>
                </div>

                @if ($destination->hasCoordinates())
                    <div class="rounded-xl overflow-hidden h-64 border border-gray-200 bg-gray-100 relative z-0" id="detail-map"></div>
                @else
                    <div class="rounded-xl overflow-hidden h-64 border border-gray-200 bg-gray-100 flex items-center justify-center text-sm text-gray-500">
                        No coordinates recorded for this destination.
                    </div>
                @endif

                @if ($destination->directions !== '')
                    <div class="mt-4 bg-gray-50 border border-gray-100 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-900 mb-1">🧭 How to get there</p>
                        <p class="text-xs text-gray-600 leading-relaxed">{{ $destination->directions }}</p>
                    </div>
                @endif
            </div>

            <!-- Related -->
            <!-- Other Categories -->
@if (!empty($otherTypes))
<div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
    <div class="flex justify-between items-end gap-4 mb-6">
        <div>
            <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-1">Explore More</p>
            <h2 class="text-2xl font-serif font-bold text-gray-900">Discover other kinds of experiences</h2>
            <p class="text-xs text-gray-500 mt-1">Beyond {{ implode(', ', $destination->types) }}: try {{ implode(', ', $otherTypes) }}.</p>
        </div>
        <a href="{{ route('destinations', ['jenis' => $otherTypes[0]]) }}" class="text-xs font-bold text-brand-accent hover:underline whitespace-nowrap">View category →</a>
    </div>

    @if ($crossCategory->isEmpty())
        <p class="text-sm text-gray-500">No destinations from other categories found in the ontology.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($crossCategory as $item)
                <x-destination-card :destination="$item" :show-description="false" />
            @endforeach
        </div>
    @endif
</div>
@endif
        </div>
    </div>
</div>
@endsection

@push('styles')
@if ($destination->hasCoordinates())
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endif
@endpush

@push('scripts')
<script>
    function shareDestination() {
        const shareData = {
            title: @json($destination->name),
            text: @json($destination->shortDescription),
            url: window.location.href,
        };

        if (navigator.share) {
            navigator.share(shareData).catch(() => {});
            return;
        }

        navigator.clipboard?.writeText(window.location.href)
            .then(() => alert('Link copied to clipboard.'))
            .catch(() => alert('Copy this page URL to share it.'));
    }
</script>

@if ($destination->hasCoordinates())
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latitude = {{ $destination->latitude }};
            const longitude = {{ $destination->longitude }};
            const map = L.map('detail-map', { scrollWheelZoom: false }).setView([latitude, longitude], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            L.marker([latitude, longitude])
                .addTo(map)
                .bindPopup(@json('<strong>'.e($destination->name).'</strong><br>'.e($destination->location)))
                .openPopup();
        });
    </script>
@endif
@endpush