@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full overflow-x-hidden">

    <!-- Header -->
    <div class="mb-10">
        <span class="inline-block bg-brand-light border border-brand-primary/20 text-brand-primary text-xs font-bold px-3 py-1 rounded-full mb-4">
            <span class="w-2 h-2 rounded-full bg-green-500 inline-block mr-1"></span>
            Live Ontology Dashboard
        </span>
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6">
            <div>
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Explore North Sumatra &amp; Aceh through linked data</h1>
                <p class="text-gray-600 text-lg max-w-2xl">
                    {{ number_format($facets['total']) }} destinations curated from the tourism ontology, covering volcanic highlands, Batak heritage, and the Acehnese coast.
                </p>
            </div>
            <form action="{{ route('destinations') }}" method="GET" class="flex w-full lg:w-auto gap-2">
                <div class="relative flex-grow lg:w-72">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                    <input type="text" name="q" placeholder="Search destinations..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:border-brand-primary">
                </div>
                <button type="submit" class="bg-brand-dark hover:bg-gray-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition">Search</button>
            </form>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Total Destinations</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ number_format($facets['total']) }}</h3>
                <p class="text-sm text-gray-500">Across {{ collect($provinces)->filter()->count() }} regions</p>
            </div>
            <div class="bg-brand-light p-3 rounded-xl text-brand-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Categories</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ collect($facets['types'])->filter()->count() }} Types</h3>
                <p class="text-sm text-gray-500">Nature, culture, religion &amp; more</p>
            </div>
            <div class="bg-orange-50 p-3 rounded-xl text-orange-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Visitor Rating</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ $facets['average_rating'] !== null ? number_format($facets['average_rating'], 2) : '—' }} / 5</h3>
                <p class="text-sm text-gray-500">From {{ number_format($facets['rated_count']) }} rated destinations</p>
            </div>
            <div class="bg-yellow-50 p-3 rounded-xl text-yellow-600">⭐</div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Ready for the Map</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ number_format($facets['with_coordinates']) }}</h3>
                <p class="text-sm text-gray-500">Geotagged destinations</p>
            </div>
            <div class="bg-brand-light p-3 rounded-xl text-brand-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Main Column -->
        <div class="lg:col-span-2 space-y-8">

            @if ($featured)
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="relative h-72 bg-gray-300">
                        <img src="{{ $featured->imageUrl() }}" alt="{{ $featured->name }}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent"></div>
                        <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                            <span class="bg-white/20 backdrop-blur-md text-white text-xs font-bold px-3 py-1 rounded-full">TOP RATED</span>
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
                            <span>🕒 {{ $featured->openingHours !== '' ? $featured->openingHours : 'Hours pending' }}</span>
                            <span>🎟️ {{ $featured->priceLabel() }}</span>
                        </div>
                        <a href="{{ route('destination.detail', $featured->id) }}" class="bg-brand-accent hover:bg-orange-800 text-white text-sm font-bold px-5 py-2.5 rounded-full transition">
                            Explore Destination →
                        </a>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                <div class="flex justify-between items-end gap-4 mb-6">
                    <div>
                        <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-1">Curated Highlights</p>
                        <h2 class="text-3xl font-serif font-bold text-gray-900">Highest rated destinations</h2>
                    </div>
                    <a href="{{ route('destinations', ['sort' => 'rating']) }}" class="text-xs font-bold text-brand-accent hover:underline whitespace-nowrap">View all →</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach ($highlights->take(4) as $destination)
                        <x-destination-card :destination="$destination" :show-description="false" />
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                <div class="flex justify-between items-end gap-4 mb-6">
                    <div>
                        <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-1">Category Spotlight</p>
                        <h2 class="text-3xl font-serif font-bold text-gray-900">One destination from every type</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($categoryHighlights as $category)
                        <a href="{{ route('destinations', ['jenis' => $category['type']]) }}" class="group border border-gray-100 rounded-2xl p-5 bg-[#fafaf9] hover:bg-brand-light/60 hover:border-brand-primary/20 transition">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-[10px] font-bold uppercase tracking-wider bg-brand-dark text-white px-2 py-1 rounded">{{ $category['type'] }}</span>
                                <span class="text-[10px] font-bold text-gray-500">{{ $category['count'] }} destinations</span>
                            </div>
                            @if ($category['destination'])
                                <div class="flex gap-3 items-center">
                                    <img src="{{ $category['destination']->imageUrl() }}" alt="{{ $category['destination']->name }}" class="w-14 h-14 rounded-xl object-cover flex-shrink-0" loading="lazy">
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-sm text-gray-900 truncate group-hover:text-brand-primary">{{ $category['destination']->name }}</h4>
                                        <p class="text-[10px] text-gray-500 truncate">{{ $category['destination']->location }}</p>
                                        <p class="text-[10px] text-brand-primary font-bold mt-1">⭐ {{ $category['destination']->ratingLabel() }}</p>
                                    </div>
                                </div>
                            @else
                                <p class="text-xs text-gray-500">No destination has been added to this category yet.</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-8">

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Collection Breakdown</p>
                <h3 class="text-xl font-serif font-bold mb-6">Destinations by category</h3>

                <div class="space-y-4">
                    @foreach ($facets['types'] as $type => $count)
                        @php
                            $percentage = $facets['total'] > 0 ? (int) round($count / $facets['total'] * 100) : 0;
                        @endphp
                        <a href="{{ route('destinations', ['jenis' => $type]) }}" class="block group">
                            <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                                <span class="group-hover:text-brand-primary">{{ $type }}</span>
                                <span>{{ $count }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-brand-primary h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Regional Coverage</p>
                <h3 class="text-xl font-serif font-bold mb-6">Where the data lives</h3>

                <div class="space-y-3">
                    @foreach ($provinces as $province => $count)
                        <a href="{{ route('destinations', ['provinsi' => $province]) }}" class="flex justify-between items-center text-sm bg-gray-50 hover:bg-brand-light border border-gray-100 rounded-xl px-4 py-3 transition">
                            <span class="font-bold text-gray-700">{{ $province }}</span>
                            <span class="text-xs font-bold bg-white border border-gray-200 px-2 py-1 rounded">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-brand-dark text-white rounded-3xl p-6 shadow-lg">
                <p class="text-xs font-bold text-brand-accent uppercase tracking-wider mb-2">Ontology Snapshot</p>
                <h3 class="text-xl font-serif font-bold mb-4">Semantic tourism atlas</h3>

                <div class="space-y-2 text-xs text-gray-300 mb-6">
                    <div class="flex justify-between border-b border-white/10 pb-2"><span>Ontology version</span><span class="font-bold text-white">2.1.0</span></div>
                    <div class="flex justify-between border-b border-white/10 pb-2"><span>Main class</span><span class="font-bold text-white">DestinasiWisata</span></div>
                    <div class="flex justify-between border-b border-white/10 pb-2"><span>Properties</span><span class="font-bold text-white">21</span></div>
                    <div class="flex justify-between"><span>Source</span><span class="font-bold text-white">Fuseki / Turtle</span></div>
                </div>

                <a href="{{ route('map') }}" class="block text-center bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-bold px-4 py-3 rounded-xl transition">
                    Open Interactive Map →
                </a>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Data Quality</p>
                <h3 class="text-xl font-serif font-bold mb-4">Coverage at a glance</h3>
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-brand-light rounded-2xl p-4">
                        <p class="text-2xl font-bold text-brand-primary">{{ number_format($facets['with_coordinates']) }}</p>
                        <p class="text-[10px] font-bold text-gray-500 uppercase mt-1">Geotagged</p>
                    </div>
                    <div class="bg-orange-50 rounded-2xl p-4">
                        <p class="text-2xl font-bold text-brand-accent">{{ number_format($facets['with_media']) }}</p>
                        <p class="text-[10px] font-bold text-gray-500 uppercase mt-1">With media</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
