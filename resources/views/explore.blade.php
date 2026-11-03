@extends('layouts.app')

@section('content')
@php
    $activeType = $filters['jenis'] ?? '';
    $activeProvince = $filters['provinsi'] ?? '';
    $activeSort = $filters['sort'] ?? 'rating';
    $activeQuery = $filters['q'] ?? '';
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
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Explore North Sumatra &amp; Aceh Destinations</h1>
                <p class="text-gray-600 text-lg max-w-2xl">
                    Discover {{ number_format($facets['total']) }} destinations curated from the tourism ontology, spanning natural wonders, historical monuments, and cultural experiences across two iconic provinces.
                </p>
            </div>
            <div class="flex gap-3">
                <span class="bg-white border border-gray-200 text-gray-600 text-xs font-bold px-4 py-2 rounded-full shadow-sm">
                    {{ number_format($facets['with_coordinates']) }} geotagged
                </span>
                <a href="{{ route('map') }}" class="bg-white border border-gray-200 text-gray-800 text-xs font-bold px-4 py-2 rounded-full shadow-sm hover:bg-gray-50 flex items-center gap-2">
                    ↗ Open Map Atlas
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-8 space-y-4">
        <form action="{{ route('destinations') }}" method="GET" class="flex flex-col lg:flex-row gap-4 justify-between items-center">
            @if ($activeType !== '')
                <input type="hidden" name="jenis" value="{{ $activeType }}">
            @endif
            @if ($activeProvince !== '')
                <input type="hidden" name="provinsi" value="{{ $activeProvince }}">
            @endif

            <div class="flex-grow w-full flex flex-col sm:flex-row gap-3">
                <div class="relative flex-grow">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                    <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Filter by attraction name, caldera, or island..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-brand-primary">
                </div>
                <select name="sort" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-48">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($activeSort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-brand-dark hover:bg-gray-800 text-white text-xs font-bold px-5 py-2.5 rounded-lg transition whitespace-nowrap">
                    Apply Filters
                </button>
            </div>

            <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                <span>Showing {{ $firstItem }}–{{ $lastItem }} of {{ number_format($destinations->total()) }}</span>
                @if ($filters !== [])
                    <a href="{{ route('destinations') }}" class="text-brand-accent hover:underline">Reset</a>
                @endif
            </div>
        </form>

        <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
            <a href="{{ $urlFor(['jenis' => null, 'provinsi' => null, 'page' => null]) }}" class="text-xs font-bold px-3 py-1.5 rounded-full transition {{ $activeType === '' && $activeProvince === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                All Regions ({{ number_format($facets['total']) }})
            </a>
            @foreach ($provinces as $province)
                <a href="{{ $urlFor(['provinsi' => $province, 'jenis' => null, 'page' => null]) }}" class="text-xs font-bold px-3 py-1.5 rounded-full transition {{ $activeProvince === $province ? 'bg-brand-primary text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                    {{ $province }} ({{ number_format($facets['provinces'][$province] ?? 0) }})
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
            <a href="{{ $urlFor(['jenis' => null, 'page' => null]) }}" class="text-xs font-bold px-3 py-1.5 rounded-full transition {{ $activeType === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                All Categories
            </a>
            @foreach ($types as $type)
                <a href="{{ $urlFor(['jenis' => $type, 'page' => null]) }}" class="text-xs font-medium px-3 py-1.5 rounded-full transition {{ $activeType === $type ? 'bg-brand-primary text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                    {{ $type }} ({{ number_format($facets['types'][$type] ?? 0) }})
                </a>
            @endforeach
        </div>
    </div>

    <!-- Destination Grid -->
    @if ($destinations->isEmpty())
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-12 text-center mb-10">
            <p class="text-4xl mb-4">🔎</p>
            <h2 class="text-2xl font-serif font-bold text-gray-900 mb-2">No destinations match your filters</h2>
            <p class="text-sm text-gray-500 mb-6">Try removing a category, changing the region, or using a broader keyword.</p>
            <a href="{{ route('destinations') }}" class="inline-block bg-brand-accent hover:bg-orange-800 text-white text-sm font-bold px-5 py-2.5 rounded-full transition">Reset all filters</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
            @foreach ($destinations as $destination)
                <x-destination-card :destination="$destination" />
            @endforeach
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 border-t border-gray-200 pt-6 mb-16">
            <span class="text-sm text-gray-500">
                Showing {{ $firstItem }}–{{ $lastItem }} of {{ number_format($destinations->total()) }} attractions
            </span>
            <div>
                {{ $destinations->links() }}
            </div>
        </div>
    @endif

    <!-- Banner CTA -->
    <div class="bg-brand-dark text-white rounded-3xl p-8 md:p-12 relative overflow-hidden flex flex-col md:flex-row justify-between items-center gap-8 shadow-lg">
        <div class="relative z-10">
            <p class="text-xs font-bold text-brand-accent tracking-widest uppercase mb-2">🗺️ PLAN YOUR ROUTE</p>
            <h3 class="text-3xl md:text-4xl font-serif font-bold mb-4">See every destination on the interactive map</h3>
            <p class="text-gray-300 text-sm max-w-2xl">Filter by category and province, then open each marker to jump straight into the destination detail page.</p>
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
