@extends('layouts.app')

@php
    $googleMapsKey = config('services.google_maps.key');
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
    $mapConfig = [
        'markers' => $markers,
        'colors' => $typeColors,
        'googleMapsKey' => $googleMapsKey,
        'routeComputeUrl' => route('routes.compute'),
    ];
@endphp

@section('content')
<div class="flex h-[calc(100vh-80px)] overflow-hidden relative">

    <!-- SIDEBAR -->
    <div id="map-sidebar" class="w-[400px] max-md:w-[88vw] max-md:absolute max-md:inset-y-0 max-md:left-0 max-md:z-[1200] bg-white border-r border-gray-200 flex-col z-10 shadow-lg hidden md:flex min-h-0">
        <div data-map-browser-header class="p-5 border-b border-gray-100 space-y-4 bg-[#f8faf9]">
            <div class="flex justify-between items-start gap-3">
                <div>
                    <p class="text-[10px] font-bold text-brand-accent uppercase tracking-wider">Atlas Destinasi</p>
                    <h1 class="text-xl font-serif font-bold text-gray-900">Sumatera Utara</h1>
                </div>
                <button type="button" data-toggle-sidebar="false" class="md:hidden w-8 h-8 rounded-full border border-gray-200 text-gray-500 hover:bg-gray-50" aria-label="Tutup daftar destinasi">✕</button>
            </div>

            <form action="{{ route('map') }}" method="GET" class="flex items-center gap-2" data-live-map data-live-url="{{ route('map.live') }}" autocomplete="off" role="search" aria-label="Cari destinasi di peta">
                @if ($activeType !== '')
                    <input type="hidden" name="jenis" value="{{ $activeType }}">
                @endif
                <div class="relative min-w-0 flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">🔍</span>
                    <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Cari destinasi yang ditampilkan..." class="w-full pl-10 pr-3 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium outline-none focus:border-brand-primary" autocomplete="off" aria-label="Cari destinasi yang ditampilkan di peta">
                </div>
                <button type="submit" class="shrink-0 bg-brand-dark hover:bg-gray-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl">Cari</button>
            </form>
            <p id="map-live-status" class="sr-only" role="status" aria-live="polite"></p>

            <div class="flex gap-2 overflow-x-auto pb-1" data-map-category-pills>
                <a href="{{ $urlFor(['jenis' => null]) }}" data-jenis="" class="text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap transition {{ $activeType === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-gray-300' }}">
                    Semua
                </a>
                @foreach ($types as $type)
                    <a href="{{ $urlFor(['jenis' => $type]) }}" data-jenis="{{ $type }}" class="text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap flex items-center gap-1 transition {{ $activeType === $type ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-gray-300' }}">
                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $typeColors[$type] }}"></span>
                        {{ $type }}
                    </a>
                @endforeach
            </div>
        </div>

        <section id="map-route-panel" class="hidden flex-grow min-h-0 flex-col bg-white" aria-label="Petunjuk arah perjalanan">
            <header class="flex items-center justify-between gap-3 p-4 border-b border-gray-200">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-brand-primary">Rute perjalanan</p>
                    <h2 class="text-lg font-bold text-gray-900">Petunjuk Arah</h2>
                </div>
                <button type="button" data-route-close class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Kembali ke daftar destinasi" title="Kembali ke daftar destinasi">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </header>

            <div class="flex-grow overflow-y-auto p-4 space-y-4">
                <fieldset>
                    <legend class="mb-2 text-xs font-bold text-gray-700">Pilih moda transportasi</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" data-route-mode="DRIVE" aria-pressed="true" class="rounded-lg border border-blue-600 bg-blue-50 px-2 py-2 text-xs font-bold text-blue-800">🚗 Mobil</button>
                        <button type="button" data-route-mode="TWO_WHEELER" aria-pressed="false" class="rounded-lg border border-gray-200 bg-white px-2 py-2 text-xs font-bold text-gray-700">🏍️ Motor</button>
                        <button type="button" data-route-mode="WALK" aria-pressed="false" class="rounded-lg border border-gray-200 bg-white px-2 py-2 text-xs font-bold text-gray-700">🚶 Jalan kaki</button>
                    </div>
                </fieldset>

                <div class="space-y-3">
                    <div>
                        <label for="route-origin-select" class="mb-1 block text-xs font-bold text-gray-700">Titik asal</label>
                        <div class="flex gap-2">
                            <select id="route-origin-select" class="min-w-0 flex-grow rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                                <option value="">Pilih titik awal</option>
                                <option value="__current">Lokasi Anda</option>
                                <option value="__medan">Pusat Kota Medan</option>
                            </select>
                            <button type="button" data-route-current-location class="shrink-0 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50" title="Gunakan lokasi perangkat">📍 Lokasi Anda</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="min-w-0 flex-grow rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-gray-500">Tujuan</span>
                            <span id="route-destination-name" class="block truncate text-sm font-bold text-gray-900">Pilih destinasi</span>
                            <span id="route-destination-location" class="block truncate text-xs text-gray-500">—</span>
                        </div>
                        <button type="button" data-route-swap class="w-10 h-10 shrink-0 rounded-lg border border-gray-200 text-lg font-bold text-gray-700 hover:bg-gray-50" aria-label="Tukar titik asal dan tujuan" title="Tukar titik asal dan tujuan">⇅</button>
                    </div>
                </div>

                <p id="route-planner-status" class="text-xs text-gray-600" role="status" aria-live="polite">Pilih titik asal atau gunakan lokasi Anda untuk menghitung rute.</p>
                <div id="route-options" class="space-y-2" aria-label="Pilihan rute"></div>
                <details id="route-steps" class="hidden rounded-xl border border-gray-200">
                    <summary class="cursor-pointer px-3 py-2 text-sm font-bold text-gray-800">Rincian langkah perjalanan</summary>
                    <ol id="route-steps-list" class="list-decimal space-y-2 border-t border-gray-100 px-7 py-3 text-xs leading-relaxed text-gray-700"></ol>
                </details>
            </div>

            <footer class="border-t border-gray-200 p-3">
                <button type="button" data-route-reset class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50">Reset / Tutup Rute</button>
            </footer>
        </section>

        <div id="map-results" class="flex-grow overflow-y-auto p-4 space-y-4 bg-[#f8faf9]" role="region" aria-label="Hasil destinasi di peta" aria-busy="false">
            @include('partials.map-results', ['results' => $results, 'matchCount' => $matchCount, 'searchPlan' => $searchPlan, 'routeName' => 'map'])
        </div>

        <div data-map-browser-footer class="p-3 bg-white border-t border-gray-200 flex justify-between items-center text-xs font-bold">
            <span class="text-green-600 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 animate-pulse" aria-hidden="true"></span> <span id="map-marker-count">{{ \App\Support\IndonesianFormat::number(count($markers)) }}</span> titik pada peta</span>
            <a href="{{ route('map') }}" class="text-gray-500 hover:text-gray-900">Hapus filter</a>
        </div>
    </div>

    <!-- MAP AREA -->
    <div class="flex-grow relative bg-[#e5e9e2] overflow-hidden">
        <button type="button" data-toggle-sidebar="true" class="md:hidden absolute top-6 left-6 z-[1000] bg-white text-brand-dark px-4 py-2 rounded-lg shadow-md text-xs font-bold border border-gray-200" aria-label="Buka daftar destinasi">
            ☰ Destinasi (<span id="map-sidebar-count">{{ \App\Support\IndonesianFormat::number($matchCount) }}</span>)
        </button>

        <div class="w-full h-full relative z-0" id="map" role="region" aria-label="Peta interaktif destinasi di Sumatera Utara">
            <div data-map-legend class="absolute top-4 left-4 z-[1000] bg-white rounded-xl shadow-lg border border-gray-100 p-4 w-64">
                <div data-map-legend-handle class="flex justify-between items-center mb-4 cursor-grab active:cursor-grabbing select-none touch-none">
                    <h2 class="font-bold text-xs text-gray-800 flex items-center gap-1">◈ LEGENDA PETA</h2>
                    <span class="text-lg leading-none text-gray-400" aria-hidden="true">⋮⋮</span>
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
                <div class="border-t border-gray-100 mt-3 pt-3 flex items-center justify-between gap-2">
                    <span class="text-[10px] text-gray-500">Tampilan penanda</span>
                    <button type="button" data-map-all class="rounded-lg bg-white border border-gray-200 px-3 py-1.5 text-[9px] font-bold text-gray-700 hover:bg-gray-50" title="Tampilkan penanda satu per satu" aria-label="Tampilkan penanda satu per satu" aria-pressed="false">SATUAN</button>
                </div>
                <div class="mt-2 text-[10px] text-gray-500">
                    {{ \App\Support\IndonesianFormat::number($facets['with_coordinates']) }} destinasi memiliki koordinat.
                </div>
            </div>
        </div>

        <div class="absolute bottom-0 left-0 w-full bg-white/90 backdrop-blur border-t border-gray-200 px-6 py-2 flex justify-between items-center text-xs text-gray-600 z-[1000]">
            <span>Kaldera Danau Toba dan Sumatera Utara</span>
            <div class="flex gap-4 items-center">
                <span><span id="map-footer-marker-count">{{ \App\Support\IndonesianFormat::number(count($markers)) }}</span> penanda</span>
                <span class="hidden sm:inline"><span id="map-footer-match-count">{{ \App\Support\IndonesianFormat::number($matchCount) }}</span> destinasi ditemukan</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.WayFinderMapConfig = @json($mapConfig);
</script>
@endpush