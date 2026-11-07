@extends('layouts.app')

@php
    $activeType = $filters['jenis'] ?? '';
    $activeQuery = $filters['q'] ?? '';
    $urlFor = function (array $overrides = []) use ($filters): string {
        $params = array_merge($filters, $overrides);

        return route('map', array_filter($params, static fn ($value): bool => $value !== null && $value !== ''));
    };
    $typeClasses = [
        'Alam' => 'bg-green-700 text-white',
        'Budaya' => 'bg-amber-800 text-white',
        'Religi' => 'bg-gray-700 text-white',
        'Bahari' => 'bg-blue-700 text-white',
        'Kuliner' => 'bg-yellow-400 text-gray-900',
        'Buatan' => 'bg-purple-700 text-white',
    ];
    $typeColors = [
        'Alam' => '#15803d',
        'Budaya' => '#92400e',
        'Religi' => '#374151',
        'Bahari' => '#1d4ed8',
        'Kuliner' => '#facc15',
        'Buatan' => '#7e22ce',
    ];
@endphp

@section('content')
<div class="flex h-[calc(100vh-80px)] overflow-hidden relative">

    <!-- SIDEBAR -->
    <div id="map-sidebar" class="w-[400px] max-md:w-[88vw] max-md:absolute max-md:inset-y-0 max-md:left-0 max-md:z-[1200] bg-white border-r border-gray-200 flex-col z-10 shadow-lg hidden md:flex">
        <div class="p-5 border-b border-gray-100 space-y-4 bg-[#f8faf9]">
            <div class="flex justify-between items-start gap-3">
                <div>
                    <p class="text-[10px] font-bold text-brand-accent uppercase tracking-wider">Destination Atlas</p>
                    <h1 class="text-xl font-serif font-bold text-gray-900">North Sumatera</h1>
                </div>
                <button type="button" onclick="toggleSidebar(false)" class="md:hidden w-8 h-8 rounded-full border border-gray-200 text-gray-500 hover:bg-gray-50" aria-label="Close destination list">✕</button>
            </div>

            <form action="{{ route('map') }}" method="GET" class="relative" data-live-map data-live-url="{{ route('map.live') }}" autocomplete="off">
                @if ($activeType !== '')
                    <input type="hidden" name="jenis" value="{{ $activeType }}">
                @endif
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Search mapped destinations..." class="w-full pl-10 pr-20 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium outline-none focus:border-brand-primary" autocomplete="off">
                <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 bg-brand-dark hover:bg-gray-800 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg">Find</button>
            </form>

            <div class="flex gap-2 overflow-x-auto pb-1">
                <a href="{{ $urlFor(['jenis' => null]) }}" class="text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap transition {{ $activeType === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-gray-300' }}">
                    All ({{ number_format($facets['total']) }})
                </a>
                @foreach ($types as $type)
                    <a href="{{ $urlFor(['jenis' => $type]) }}" class="text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap flex items-center gap-1 transition {{ $activeType === $type ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-gray-300' }}">
                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $typeColors[$type] }}"></span>
                        {{ $type }} ({{ number_format($facets['types'][$type] ?? 0) }})
                    </a>
                @endforeach
            </div>
        </div>

        <div id="map-results" class="flex-grow overflow-y-auto p-4 space-y-4 bg-[#f8faf9]">
            @include('partials.map-results', ['results' => $results, 'matchCount' => $matchCount])
        </div>

        <div class="p-3 bg-white border-t border-gray-200 flex justify-between items-center text-xs font-bold">
            <span class="text-green-600 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> <span id="map-marker-count">{{ number_format(count($markers)) }}</span> mapped points</span>
            <a href="{{ route('map') }}" class="text-gray-500 hover:text-gray-900">Clear Filters</a>
        </div>
    </div>

    <!-- MAP AREA -->
    <div class="flex-grow relative bg-[#e5e9e2] overflow-hidden">
        <button type="button" onclick="toggleSidebar(true)" class="md:hidden absolute top-6 left-6 z-[1000] bg-white text-brand-dark px-4 py-2 rounded-lg shadow-md text-xs font-bold border border-gray-200">
            ☰ Destinations (<span id="map-sidebar-count">{{ number_format($matchCount) }}</span>)
        </button>

        <div class="absolute top-6 right-6 z-[1000] bg-white rounded-xl shadow-lg border border-gray-100 p-4 w-64 hidden lg:block">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-xs text-gray-800 flex items-center gap-1">◈ ATLAS LEGEND</h4>
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
            </div>
            <div class="space-y-2">
                @foreach ($types as $type)
                    <a href="{{ $urlFor(['jenis' => $type]) }}" class="flex items-center justify-between text-xs font-medium text-gray-600 hover:text-brand-primary">
                        <span class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $typeColors[$type] }}"></span>
                            {{ $type }}
                        </span>
                        <span class="text-[10px] font-bold text-gray-400">{{ number_format($facets['types'][$type] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
            <div class="border-t border-gray-100 mt-3 pt-3 text-[10px] text-gray-500">
                {{ number_format($facets['with_coordinates']) }} destinations have coordinates in the ontology.
            </div>
        </div>

        <div class="w-full h-full z-0" id="map"></div>

        <div class="absolute bottom-12 right-6 z-[1000] flex flex-col gap-2">
            <button type="button" onclick="fitMap()" class="w-10 h-10 bg-white rounded-lg shadow-md flex items-center justify-center text-gray-600 hover:bg-gray-50" title="Fit all markers">🎯</button>
            <a href="{{ route('destinations') }}" class="w-10 h-10 bg-brand-accent text-white rounded-lg shadow-md flex items-center justify-center hover:bg-orange-800" title="Open destination directory">👥</a>
        </div>

        <div class="absolute bottom-0 left-0 w-full bg-white/90 backdrop-blur border-t border-gray-200 px-6 py-2 flex justify-between items-center text-xs text-gray-600 z-[1000]">
            <span>Caldera Toba Geopark &amp; Serambi Mekkah</span>
            <div class="flex gap-4 items-center">
                <span><span id="map-footer-marker-count">{{ number_format(count($markers)) }}</span> markers</span>
                <span class="hidden sm:inline"><span id="map-footer-match-count">{{ number_format($matchCount) }}</span> destinations match</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const markerData = @json($markers);
    const markerColors = @json($typeColors);
    const map = L.map('map').setView([2.8, 98.6], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const markers = {};
    let bounds = [];

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[character]));

    function clearMarkers() {
        Object.values(markers).forEach((marker) => map.removeLayer(marker));
        Object.keys(markers).forEach((id) => delete markers[id]);
        bounds = [];
    }

    function renderMarkers(destinations) {
        clearMarkers();

        destinations.forEach((destination) => {
            const color = markerColors[destination.type] || '#0f5132';
            const icon = L.divIcon({
                className: '',
                html: `<div style="width:18px;height:18px;border-radius:9999px;background:${color};border:2px solid #ffffff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });

            const marker = L.marker([destination.latitude, destination.longitude], { icon }).addTo(map);

            marker.bindPopup(`
                <div style="min-width:180px">
                    <img src="${escapeHtml(destination.image)}" alt="${escapeHtml(destination.name)}" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:8px">
                    <strong style="display:block;font-size:13px">${escapeHtml(destination.name)}</strong>
                    <span style="font-size:11px;color:#4b5563">${escapeHtml(destination.province)} • ${escapeHtml(destination.type)}</span><br>
                    <span style="font-size:11px;color:#4b5563">⭐ ${escapeHtml(destination.rating_label)} • ${escapeHtml(destination.price)}</span><br>
                    <a href="${escapeHtml(destination.url)}" style="display:inline-block;margin-top:6px;font-size:11px;font-weight:700;color:#b74a1c">View details →</a>
                </div>
            `);

            markers[destination.id] = marker;
            bounds.push([destination.latitude, destination.longitude]);
        });
    }

    function fitMap() {
        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40] });
        } else {
            map.setView([2.8, 98.6], 7);
        }
    }

    function focusMarker(id) {
        const marker = markers[id];

        if (! marker) {
            return;
        }

        map.flyTo(marker.getLatLng(), 13, { duration: 0.8 });
        marker.openPopup();

        if (window.innerWidth < 768) {
            toggleSidebar(false);
        }
    }

    function toggleSidebar(show) {
        const sidebar = document.getElementById('map-sidebar');

        if (! sidebar) {
            return;
        }

        if (show) {
            sidebar.classList.remove('hidden');
            sidebar.classList.add('flex');
        } else {
            sidebar.classList.add('hidden');
            sidebar.classList.remove('flex');
        }
    }

    window.LiveMap = {
        update(destinations) {
            renderMarkers(destinations);
            fitMap();
        }
    };

    renderMarkers(markerData);
    fitMap();
</script>
@endpush