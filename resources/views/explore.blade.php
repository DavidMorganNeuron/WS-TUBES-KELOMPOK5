@extends('layouts.app')

@section('content')
@php
    $activeType = $filters['jenis'] ?? '';
    $activeKabupaten = $filters['kabupaten'] ?? '';
    $activeSort = $filters['sort'] ?? 'name';
    $activeQuery = $filters['q'] ?? '';
    $hasActiveFilters = $activeQuery !== '' || $activeType !== '' || $activeKabupaten !== '' || $activeSort !== 'name';
    $urlFor = function (array $overrides = []) use ($filters): string {
        $params = array_merge($filters, $overrides);

        return route('destinations', array_filter($params, static fn ($value): bool => $value !== null && $value !== ''));
    };
    $firstItem = $destinations->firstItem() ?? 0;
    $lastItem = $destinations->lastItem() ?? 0;
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full overflow-x-hidden">

    <!-- Header -->
    <div class="mb-8">
        <div class="text-xs text-gray-500 font-medium mb-4 flex items-center gap-2">
            <a href="{{ route('home') }}" class="hover:text-brand-primary">Home</a> <span>›</span>
            <span class="text-brand-primary font-bold">Destinations Directory</span>
        </div>

        <span class="inline-flex items-center gap-2 text-brand-accent text-xs font-bold uppercase tracking-wider mb-2">
            🎯 REGIONAL COMPREHENSIVE ATLAS
        </span>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Explore North Sumatra Destinations</h1>
                <p class="text-gray-600 text-lg max-w-2xl">
                    Discover {{ number_format($facets['total']) }} destinations curated from the tourism ontology, spanning natural wonders, historical monuments, and cultural experiences across North Sumatra.
                </p>
            </div>
            <div class="flex gap-3">
                <span class="bg-white border border-gray-200 text-gray-600 text-xs font-bold px-4 py-2 rounded-full shadow-sm">
                    {{ number_format($facets['with_coordinates']) }} geotagged
                </span>
                <a href="{{ route('map') }}" class="bg-white border border-gray-200 text-gray-800 text-xs font-bold px-4 py-2 rounded-full shadow-sm hover:bg-gray-50 flex items-center gap-2">
                    Open Map Atlas
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-8 space-y-4">
        <form id="destination-filters" action="{{ route('destinations') }}" method="GET" class="flex flex-col lg:flex-row gap-4 justify-between items-center" data-live-destinations data-live-url="{{ route('destinations.live') }}" autocomplete="off" role="search">
            @if ($activeType !== '')
                <input type="hidden" name="jenis" value="{{ $activeType }}">
            @endif

            <div class="flex-grow w-full flex flex-col sm:flex-row sm:flex-wrap lg:flex-nowrap gap-3">
                <div class="relative flex-grow min-w-[12rem]">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">🔍</span>
                    <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Filter by attraction name, caldera, or island..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-brand-primary" autocomplete="off" aria-label="Filter destinations by name">
                </div>
                <select name="sort" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-48" aria-label="Sort destinations">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($activeSort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="kabupaten" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-56" aria-label="Filter by regency or city">
                    <option value="">All regencies &amp; cities</option>
                    @foreach (array_keys($facets['kabupaten']) as $kabupaten)
                        <option value="{{ $kabupaten }}" @selected($activeKabupaten === $kabupaten)>{{ $kabupaten }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                <span id="destination-showing" role="status" aria-live="polite">Showing {{ $firstItem }}–{{ $lastItem }} of {{ number_format($destinations->total()) }}</span>
                <a id="destination-reset" href="{{ route('destinations') }}" class="text-brand-accent hover:underline {{ $hasActiveFilters ? '' : 'hidden' }}">Reset</a>
            </div>
        </form>

        <p id="destination-live-status" class="sr-only" role="status" aria-live="polite"></p>

        <div class="flex flex-wrap justify-center gap-2 pt-2 border-t border-gray-100" data-category-pills>
            <a href="{{ $urlFor(['jenis' => null, 'page' => null]) }}" data-jenis="" class="text-xs font-bold px-3 py-1.5 rounded-full transition {{ $activeType === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                All Categories
            </a>
            @foreach ($types as $type)
                <a href="{{ $urlFor(['jenis' => $type, 'page' => null]) }}" data-jenis="{{ $type }}" class="text-xs font-medium px-3 py-1.5 rounded-full transition {{ $activeType === $type ? 'bg-brand-primary text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                    {{ $type }} ({{ number_format($facets['types'][$type] ?? 0) }})
                </a>
            @endforeach
        </div>
    </div>

    <!-- Destination Grid -->
    <div id="destination-results" aria-busy="false">
        @include('partials.destination-results', ['destinations' => $destinations])
    </div>

    <!-- Banner CTA -->
    <div class="bg-brand-dark text-white rounded-3xl p-8 md:p-12 relative overflow-hidden flex flex-col md:flex-row justify-between items-center gap-8 shadow-lg">
        <div class="relative z-10">
            <p class="text-xs font-bold text-brand-accent tracking-widest uppercase mb-2">🗺️ PLAN YOUR ROUTE</p>
            <h3 class="text-3xl md:text-4xl font-serif font-bold mb-4">See every destination on the interactive map</h3>
            <p class="text-gray-300 text-sm max-w-2xl">Explore destinations by category and province on the map, then open each marker to jump straight into the destination detail page.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 relative z-10 w-full md:w-auto">
            <a href="{{ route('map') }}" class="bg-brand-accent hover:bg-orange-800 text-white px-6 py-3 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2">
                Open Map View
            </a>
            <a href="{{ route('home') }}" class="bg-white/10 hover:bg-white/20 border border-white/20 text-white px-6 py-3 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2">
                Back to Dashboard
            </a>
        </div>
    </div>

</div>
@endsection
