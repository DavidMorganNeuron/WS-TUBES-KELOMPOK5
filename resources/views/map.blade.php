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
                    <h1 class="text-xl font-serif font-bold text-gray-900">North Sumatra</h1>
                </div>
                <button type="button" data-toggle-sidebar="false" class="md:hidden w-8 h-8 rounded-full border border-gray-200 text-gray-500 hover:bg-gray-50" aria-label="Close destination list">✕</button>
            </div>

            <form action="{{ route('map') }}" method="GET" class="relative" data-live-map data-live-url="{{ route('map.live') }}" autocomplete="off" role="search">
                @if ($activeType !== '')
                    <input type="hidden" name="jenis" value="{{ $activeType }}">
                @endif
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">🔍</span>
                <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Search mapped destinations..." class="w-full pl-10 pr-20 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium outline-none focus:border-brand-primary" autocomplete="off" aria-label="Search mapped destinations">
                <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 bg-brand-dark hover:bg-gray-800 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg">Find</button>
            </form>
            <p id="map-live-status" class="sr-only" role="status" aria-live="polite"></p>

            <div class="flex gap-2 overflow-x-auto pb-1" data-map-category-pills>
                <a href="{{ $urlFor(['jenis' => null]) }}" data-jenis="" class="text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap transition {{ $activeType === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-gray-300' }}">
                    All ({{ number_format($facets['total']) }})
                </a>
                @foreach ($types as $type)
                    <a href="{{ $urlFor(['jenis' => $type]) }}" data-jenis="{{ $type }}" class="text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap flex items-center gap-1 transition {{ $activeType === $type ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-gray-300' }}">
                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $typeColors[$type] }}"></span>
                        {{ $type }} ({{ number_format($facets['types'][$type] ?? 0) }})
                    </a>
                @endforeach
            </div>
        </div>

        <div id="map-results" class="flex-grow overflow-y-auto p-4 space-y-4 bg-[#f8faf9]" aria-busy="false">
            @include('partials.map-results', ['results' => $results, 'matchCount' => $matchCount])
        </div>

        <div class="p-3 bg-white border-t border-gray-200 flex justify-between items-center text-xs font-bold">
            <span class="text-green-600 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> <span id="map-marker-count">{{ number_format(count($markers)) }}</span> mapped points</span>
            <a href="{{ route('map') }}" class="text-gray-500 hover:text-gray-900">Clear Filters</a>
        </div>
    </div>

    <!-- MAP AREA -->
    <div class="flex-grow relative bg-[#e5e9e2] overflow-hidden">
        <button type="button" data-toggle-sidebar="true" class="md:hidden absolute top-6 left-6 z-[1000] bg-white text-brand-dark px-4 py-2 rounded-lg shadow-md text-xs font-bold border border-gray-200">
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

        <div class="w-full h-full z-0" id="map" role="application" aria-label="Interactive map of destinations in North Sumatra"></div>

        <div class="absolute bottom-12 right-6 z-[1000] flex flex-col gap-2">
            <button type="button" data-map-fit class="w-10 h-10 bg-white rounded-lg shadow-md flex items-center justify-center text-gray-600 hover:bg-gray-50" title="Fit all markers" aria-label="Fit all markers on the map">🎯</button>
            <button type="button" data-map-all class="w-10 h-10 bg-white rounded-lg shadow-md flex items-center justify-center text-xs font-bold text-gray-700 hover:bg-gray-50" title="Show markers individually" aria-label="Show markers individually" aria-pressed="false">ALL</button>
            <a href="{{ route('destinations') }}" class="w-10 h-10 bg-brand-accent text-white rounded-lg shadow-md flex items-center justify-center hover:bg-orange-800" title="Open destination directory" aria-label="Open destination directory">👥</a>
        </div>

        <div class="absolute bottom-0 left-0 w-full bg-white/90 backdrop-blur border-t border-gray-200 px-6 py-2 flex justify-between items-center text-xs text-gray-600 z-[1000]">
            <span>Lake Toba Caldera &amp; North Sumatra</span>
            <div class="flex gap-4 items-center">
                <span><span id="map-footer-marker-count">{{ number_format(count($markers)) }}</span> markers</span>
                <span class="hidden sm:inline"><span id="map-footer-match-count">{{ number_format($matchCount) }}</span> destinations match</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.WayFinderMapConfig = @json([
        'markers' => $markers,
        'colors' => $typeColors,
    ]);
</script>
@endpush