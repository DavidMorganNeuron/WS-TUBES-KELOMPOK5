@extends('layouts.app')

@section('content')
@php
    $activeType = $filters['jenis'] ?? '';
    $activeKabupaten = $filters['kabupaten'] ?? '';
    $activeSort = $filters['sort'] ?? '';
    $activeQuery = $filters['q'] ?? '';
    $activeActivity = $filters['dapatMelakukan'] ?? '';
    $activeFacility = $filters['memilikiFasilitas'] ?? '';
    $hasActiveFilters = $activeQuery !== '' || $activeType !== '' || $activeKabupaten !== '' || $activeActivity !== '' || $activeFacility !== '' || ($activeSort !== '' && $activeSort !== 'name');
    $urlFor = function (array $overrides = []) use ($filters): string {
        $params = array_merge($filters, $overrides);

        return route('destinations', array_filter($params, static fn ($value): bool => $value !== null && $value !== ''));
    };
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full overflow-x-hidden">

    <!-- Header -->
    <div class="mb-8">
        <div class="text-xs text-gray-500 font-medium mb-4 flex items-center gap-2">
            <a href="{{ route('home') }}" class="hover:text-brand-primary">Beranda</a> <span aria-hidden="true">›</span>
            <span class="text-brand-primary font-bold">Direktori Destinasi</span>
        </div>

        <span class="inline-flex items-center gap-2 text-brand-accent text-xs font-bold uppercase tracking-wider mb-2">
            🎯 ATLAS LENGKAP SUMATERA UTARA
        </span>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
<<<<<<< HEAD
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Jelajahi Destinasi Sumatera Utara</h1>
                <p class="text-gray-600 text-lg max-w-2xl">
                    Temukan {{ \App\Support\IndonesianFormat::number($facets['total']) }} destinasi pilihan dari ontologi pariwisata, mulai dari keajaiban alam dan monumen bersejarah hingga pengalaman budaya di Sumatera Utara.
=======
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Explore North Sumatra Destinations</h1>
                <p class="text-gray-600 text-lg max-w-2xl">
                    Discover {{ number_format($facets['total']) }} destinations curated from the tourism ontology, spanning natural wonders, historical monuments, and cultural experiences across North Sumatra.
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                </p>
            </div>
            <div class="flex gap-3">
                <span class="bg-white border border-gray-200 text-gray-600 text-xs font-bold px-4 py-2 rounded-full shadow-sm">
                    {{ \App\Support\IndonesianFormat::number($facets['with_coordinates']) }} memiliki koordinat
                </span>
                <a href="{{ route('map') }}" class="bg-white border border-gray-200 text-gray-800 text-xs font-bold px-4 py-2 rounded-full shadow-sm hover:bg-gray-50 flex items-center gap-2">
<<<<<<< HEAD
                    Buka Peta
=======
                    Open Map Atlas
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-8 space-y-4">
<<<<<<< HEAD
        <form id="destination-filters" action="{{ route('destinations') }}" method="GET" class="flex flex-col lg:flex-row gap-4 justify-between items-center" data-live-destinations data-live-url="{{ route('destinations.live') }}" autocomplete="off" role="search" aria-label="Cari dan saring destinasi">
=======
        <form id="destination-filters" action="{{ route('destinations') }}" method="GET" class="flex flex-col lg:flex-row gap-4 justify-between items-center" data-live-destinations data-live-url="{{ route('destinations.live') }}" autocomplete="off" role="search">
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            @if ($activeType !== '')
                <input type="hidden" name="jenis" value="{{ $activeType }}">
            @endif

            <div class="flex-grow w-full flex flex-col sm:flex-row sm:flex-wrap gap-3">
                <div class="relative flex-grow min-w-[12rem]">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">🔍</span>
<<<<<<< HEAD
                    <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Cari nama objek wisata, kaldera, atau pulau..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-brand-primary" autocomplete="off" aria-label="Saring destinasi berdasarkan nama">
                </div>
                <select name="sort" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-48" aria-label="Urutkan destinasi">
                    <option value="" hidden @selected($activeSort === '')>Urutan bawaan</option>
=======
                    <input type="text" name="q" value="{{ $activeQuery }}" placeholder="Filter by attraction name, caldera, or island..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-brand-primary" autocomplete="off" aria-label="Filter destinations by name">
                </div>
                <select name="sort" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-48" aria-label="Sort destinations">
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($activeSort === $value)>{{ [
                            'name' => 'Nama: A–Z',
                            'rating' => 'Rating: tertinggi ke terendah',
                            'rating_asc' => 'Rating: terendah ke tertinggi',
                            'price_desc' => 'Harga: tertinggi ke terendah',
                            'price_asc' => 'Harga: terendah ke tertinggi',
                        ][$value] ?? $label }}</option>
                    @endforeach
                </select>
<<<<<<< HEAD
                <select name="kabupaten" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-56" aria-label="Saring berdasarkan kabupaten atau kota">
                    <option value="">Semua kabupaten dan kota</option>
=======
                <select name="kabupaten" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-56" aria-label="Filter by regency or city">
                    <option value="">All regencies &amp; cities</option>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                    @foreach (array_keys($facets['kabupaten']) as $kabupaten)
                        <option value="{{ $kabupaten }}" @selected($activeKabupaten === $kabupaten)>{{ $kabupaten }}</option>
                    @endforeach
                </select>
<<<<<<< HEAD
                <select name="dapatMelakukan" class="bg-gray-50 border border-gray-200 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 outline-none w-full sm:w-56" aria-label="Saring berdasarkan aktivitas">
                    <option value="">Semua aktivitas</option>
                    @foreach ($filterOptions['activities'] as $activity)
                        <option value="{{ $activity }}" @selected($activeActivity === $activity)>{{ $activity }}</option>
                    @endforeach
                </select>
            </div>

=======
            </div>

            <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                <span id="destination-showing" role="status" aria-live="polite">Showing {{ $firstItem }}–{{ $lastItem }} of {{ number_format($destinations->total()) }}</span>
                <a id="destination-reset" href="{{ route('destinations') }}" class="text-brand-accent hover:underline {{ $hasActiveFilters ? '' : 'hidden' }}">Reset</a>
            </div>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        </form>

        <p id="destination-live-status" class="sr-only" role="status" aria-live="polite"></p>

        <div class="flex flex-wrap justify-center gap-2 pt-2 border-t border-gray-100" data-category-pills>
            <a href="{{ $urlFor(['jenis' => null, 'page' => null]) }}" data-jenis="" class="text-xs font-bold px-3 py-1.5 rounded-full transition {{ $activeType === '' ? 'bg-brand-dark text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
<<<<<<< HEAD
                Semua Kategori
            </a>
            @foreach ($types as $type)
                <a href="{{ $urlFor(['jenis' => $type, 'page' => null]) }}" data-jenis="{{ $type }}" class="text-xs font-medium px-3 py-1.5 rounded-full transition {{ $activeType === $type ? 'bg-brand-primary text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                    {{ $type }} ({{ \App\Support\IndonesianFormat::number($facets['types'][$type] ?? 0) }})
=======
                All Categories
            </a>
            @foreach ($types as $type)
                <a href="{{ $urlFor(['jenis' => $type, 'page' => null]) }}" data-jenis="{{ $type }}" class="text-xs font-medium px-3 py-1.5 rounded-full transition {{ $activeType === $type ? 'bg-brand-primary text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' }}">
                    {{ $type }} ({{ number_format($facets['types'][$type] ?? 0) }})
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                </a>
            @endforeach
            <a id="destination-reset" href="{{ route('destinations') }}" class="text-xs font-medium px-3 py-1.5 rounded-full transition bg-white border border-gray-200 text-brand-accent hover:border-brand-accent {{ $hasActiveFilters ? '' : 'hidden' }}">Hapus saringan</a>
        </div>
    </div>

    <!-- Destination Grid -->
    <div id="destination-results" aria-busy="false">
<<<<<<< HEAD
        @include('partials.destination-results', ['destinations' => $destinations, 'searchPlan' => $searchPlan, 'routeName' => 'destinations'])
=======
        @include('partials.destination-results', ['destinations' => $destinations])
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    </div>

    <!-- Banner CTA -->
    <div class="bg-brand-dark text-white rounded-3xl p-8 md:p-12 relative overflow-hidden flex flex-col md:flex-row justify-between items-center gap-8 shadow-lg">
        <div class="relative z-10">
<<<<<<< HEAD
            <p class="text-xs font-bold text-brand-accent tracking-widest uppercase mb-2">🗺️ RENCANAKAN PERJALANAN</p>
            <h3 class="text-3xl md:text-4xl font-serif font-bold mb-4">Lihat semua destinasi di peta interaktif</h3>
            <p class="text-gray-300 text-sm max-w-2xl">Jelajahi destinasi berdasarkan kategori dan kabupaten atau kota, lalu pilih penanda untuk membuka detail destinasi.</p>
=======
            <p class="text-xs font-bold text-brand-accent tracking-widest uppercase mb-2">🗺️ PLAN YOUR ROUTE</p>
            <h3 class="text-3xl md:text-4xl font-serif font-bold mb-4">See every destination on the interactive map</h3>
            <p class="text-gray-300 text-sm max-w-2xl">Explore destinations by category and province on the map, then open each marker to jump straight into the destination detail page.</p>
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        </div>
        <div class="flex flex-col sm:flex-row gap-3 relative z-10 w-full md:w-auto">
            <a href="{{ route('map') }}" class="bg-brand-accent hover:bg-orange-800 text-white px-6 py-3 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2">
                Buka Peta
            </a>
            <a href="{{ route('home') }}" class="bg-white/10 hover:bg-white/20 border border-white/20 text-white px-6 py-3 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2">
                Kembali ke Beranda
            </a>
        </div>
    </div>

</div>
@endsection
