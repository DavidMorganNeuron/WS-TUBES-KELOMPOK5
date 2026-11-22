@extends('layouts.app')

@php
    $topKabupaten = $facets['kabupaten'];
    arsort($topKabupaten);
    $topKabupaten = array_slice($topKabupaten, 0, 8, true);
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full overflow-x-hidden">

    <!-- Header -->
    <div class="mb-10">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6">
            <div>
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Jelajahi destinasi Sumatera Utara</h1>
                <p class="text-gray-600 text-lg max-w-2xl">
                    {{ \App\Support\IndonesianFormat::number($facets['total']) }} destinasi pilihan dari ontologi pariwisata Sumatera Utara.
                </p>
            </div>
            <form action="{{ route('destinations') }}" method="GET" class="flex w-full lg:w-auto gap-2" data-live-suggest data-suggest-url="{{ route('destinations.suggest') }}" autocomplete="off" role="search">
                <div class="relative flex-grow lg:w-72">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">🔍</span>
                    <input type="text" name="q" placeholder="Cari destinasi..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:border-brand-primary" autocomplete="off" aria-label="Cari destinasi" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="dashboard-search-suggestions" aria-expanded="false">
                    <div id="dashboard-search-suggestions" data-suggest-panel class="absolute left-0 right-0 top-full z-50 mt-2 hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl" role="listbox" aria-label="Saran destinasi"></div>
                    <p data-suggest-status class="sr-only" role="status" aria-live="polite"></p>
                </div>
                <button type="submit" class="bg-brand-dark hover:bg-gray-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition">Cari</button>
            </form>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Total destinasi</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ \App\Support\IndonesianFormat::number($facets['total']) }}</h3>
                <p class="text-sm text-gray-500">Tersebar di {{ \App\Support\IndonesianFormat::number(count($facets['kabupaten'])) }} kabupaten/kota</p>
            </div>
            <div class="bg-brand-light p-3 rounded-xl text-brand-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Kategori</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ \App\Support\IndonesianFormat::number(collect($facets['types'])->filter()->count()) }} jenis</h3>
                <p class="text-sm text-gray-500">Alam, budaya, religi, dan lainnya</p>
            </div>
            <div class="bg-orange-50 p-3 rounded-xl text-orange-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Rata-rata penilaian</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ \App\Support\IndonesianFormat::number($facets['average_rating'], 2) }} / 5</h3>
                <p class="text-sm text-gray-500">Dari {{ \App\Support\IndonesianFormat::number($facets['rated_count']) }} destinasi yang dinilai</p>
            </div>
            <div class="bg-yellow-50 p-3 rounded-xl text-yellow-600">⭐</div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Main Column -->
        <div class="lg:col-span-2 space-y-8">

            @if ($featured)
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="relative h-72 bg-gray-200 skeleton-shimmer overflow-hidden">
                        <img src="{{ $featured->imageUrl(1600) }}" alt="{{ $featured->name }}" class="w-full h-full object-cover transition-opacity duration-300" onload="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')" onerror="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')">
                        @include('partials.image-attribution', ['destination' => $featured])
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent"></div>
                        <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                            <span class="bg-white/20 backdrop-blur-md text-white text-xs font-bold px-3 py-1 rounded-full">PENILAIAN TERTINGGI</span>
                            <span class="bg-white text-brand-primary text-xs font-bold px-3 py-1 rounded-full">{{ $featured->primaryType() }}</span>
                        </div>
                        <div class="absolute bottom-6 left-6 right-6 text-white">
                            <p class="text-xs font-bold mb-2 opacity-90 uppercase tracking-wider">{{ $featured->province() }} • {{ $featured->location }}</p>
                            <h2 class="text-3xl md:text-4xl font-serif font-bold leading-tight mb-2">{{ $featured->name }}</h2>
                            <p class="text-sm text-gray-200 max-w-2xl line-clamp-2">{{ $featured->shortDescription }}</p>
                        </div>
                    </div>
                    <div class="p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                            <span class="font-bold text-brand-primary">⭐ {{ $featured->ratingLabel() }} / 5</span>
                            @if ($featured->openingHours !== '')
                                <span>🕒 {{ $featured->openingHours }}</span>
                            @endif
                            <span>🎟️ {{ $featured->priceLabel() }}</span>
                        </div>
                        <a href="{{ route('destination.detail', $featured->id) }}" class="bg-brand-accent hover:bg-orange-800 text-white text-sm font-bold px-5 py-2.5 rounded-full transition">
                            Lihat destinasi
                        </a>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                <div class="flex justify-between items-end gap-4 mb-6">
                    <div>
                        <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-1">Pilihan kurasi</p>
                        <h2 class="text-3xl font-serif font-bold text-gray-900">Lima destinasi dengan penilaian tertinggi</h2>
                    </div>
                    <a href="{{ route('destinations', ['sort' => 'rating']) }}" class="text-xs font-bold text-brand-accent hover:underline whitespace-nowrap">Lihat semua</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach ($highlights->take(4) as $destination)
                        <x-destination-card :destination="$destination" :show-description="false" />
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="space-y-8">

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Rincian koleksi</p>
                <h3 class="text-xl font-serif font-bold mb-6">Destinasi menurut kategori</h3>

                <div class="space-y-4">
                    @foreach ($facets['types'] as $type => $count)
                        @php
                            $percentage = $facets['total'] > 0 ? (int) round($count / $facets['total'] * 100) : 0;
                        @endphp
                        <a href="{{ route('destinations', ['jenis' => $type]) }}" class="block group">
                            <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                                <span class="group-hover:text-brand-primary">{{ $type }}</span>
                                <span>{{ \App\Support\IndonesianFormat::number($count) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-brand-primary h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Cakupan wilayah</p>
                <h3 class="text-xl font-serif font-bold mb-6">Persebaran destinasi</h3>

                @if ($topRegion !== null)
                    <div class="mb-4 rounded-xl border border-brand-primary/15 bg-brand-light/60 p-4">
                        <p class="text-xs text-gray-600">Wilayah dengan destinasi terbanyak</p>
                        <p class="font-bold text-brand-dark">{{ $topRegion['label'] }}</p>
                        <p class="text-xs text-gray-600">{{ \App\Support\IndonesianFormat::number($topRegion['count']) }} destinasi</p>
                    </div>
                @endif

                <div class="space-y-3">
                    @foreach ($provinces as $province => $count)
                        <a href="{{ route('destinations', ['provinsi' => $province]) }}" class="flex justify-between items-center text-sm bg-gray-50 hover:bg-brand-light border border-gray-100 rounded-xl px-4 py-3 transition">
                            <span class="font-bold text-gray-700">{{ $province }}</span>
                            <span class="text-xs font-bold bg-white border border-gray-200 px-2 py-1 rounded">{{ \App\Support\IndonesianFormat::number($count) }}</span>
                        </a>
                    @endforeach

                    @foreach ($topKabupaten as $kabupaten => $count)
                        <a href="{{ route('destinations', ['kabupaten' => $kabupaten]) }}" class="flex justify-between items-center text-sm bg-gray-50 hover:bg-brand-light border border-gray-100 rounded-xl px-4 py-3 transition">
                            <span class="font-bold text-gray-700">{{ $kabupaten }}</span>
                            <span class="text-xs font-bold bg-white border border-gray-200 px-2 py-1 rounded">{{ \App\Support\IndonesianFormat::number($count) }}</span>
                        </a>
                    @endforeach

                    @foreach ($topKabupaten as $kabupaten => $count)
                        <a href="{{ route('destinations', ['kabupaten' => $kabupaten]) }}" class="flex justify-between items-center text-sm bg-gray-50 hover:bg-brand-light border border-gray-100 rounded-xl px-4 py-3 transition">
                            <span class="font-bold text-gray-700">{{ $kabupaten }}</span>
                            <span class="text-xs font-bold bg-white border border-gray-200 px-2 py-1 rounded">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
    @if ($topRegion !== null)
        <div class="mt-8">
            <x-info-wilayah
                :region="$wikidataRegion"
                :region-name="$topRegion['label']"
                :region-resource="$regionResource"
                :region-neighbors="$regionNeighbors"
                heading-id="dashboard-region-heading"
            />
        </div>
    @endif
</div>
@endsection
