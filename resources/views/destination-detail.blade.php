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
    $placeholderSlugs = [
        'Alam' => 'alam',
        'Budaya' => 'budaya',
        'Religi' => 'religi',
        'Bahari' => 'bahari',
        'Kuliner' => 'kuliner',
        'Buatan' => 'buatan',
    ];
    $placeholder = asset('images/placeholders/'.($placeholderSlugs[$destination->primaryType()] ?? 'destinasi').'.svg');
    $hasContact = $destination->phone !== '' || $destination->email !== '' || $destination->instagram !== '';
@endphp

@section('content')
<div
    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full overflow-x-clip"
    prefix="{{ $seo->rdfaPrefixes }}"
    typeof="schema:TouristAttraction ws:DestinasiWisata"
    resource="{{ $linkedData->iri }}"
>

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
                @include('partials.rdfa-resource-label', [
                    'resource' => $linkedData->categoryForLabel($type),
                    'relation' => 'ws:berkategori',
                    'label' => $type,
                    'class' => ($typeClasses[$type] ?? 'bg-brand-primary text-white').' text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1',
                ])
            @endforeach
            <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1">⭐ {{ $destination->ratingLabel() }}@if ($destination->rating !== null) / 5 @endif</span>
            @if ($destination->rating !== null)
                <meta property="ws:ratingPengunjung" content="{{ json_encode($destination->rating, JSON_PRESERVE_ZERO_FRACTION) }}" datatype="xsd:decimal">
            @endif
            @if ($destination->hasCoordinates())
                <span class="bg-[#e8f5e9] text-[#0f5132] text-[10px] font-bold px-3 py-1 rounded-full border border-[#0f5132]/20">📍 Geotagged</span>
            @endif
            @if ($destination->media !== '')
                <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1">📷 Ontology Media</span>
            @endif
        </div>

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6">
            <div class="min-w-0">
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-2 leading-tight" property="schema:name ws:namaDestinasi">{{ $destination->name }}</h1>
                @if ($destination->alternativeName !== '' && $destination->alternativeName !== $destination->name)
                    <p class="text-lg text-gray-500 italic mb-2">{{ $destination->alternativeName }}</p>
                @endif
                @if ($destination->shortDescription !== '')
                    <p class="text-gray-600 text-lg max-w-3xl" property="schema:description">{{ $destination->shortDescription }}</p>
                @else
                    <p class="text-gray-600 text-lg max-w-3xl">Deskripsi singkat destinasi belum tersedia.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button
                    id="btn-share-destination"
                    type="button"
                    class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm"
                    data-share-title="{{ $destination->name }}"
                    data-share-text="{{ $destination->shortDescription }}"
                >Share</button>
                <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm">📍 Google Maps</a>
                <p id="share-status" class="sr-only" role="status" aria-live="polite"></p>
            </div>
        </div>
    </div>

    @if ($seo->alternates !== [])
        <div class="mb-6 flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold text-gray-600">Data RDF:</span>
            @foreach ($seo->alternates as $alternate)
                <a href="{{ $alternate['url'] }}" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 font-semibold text-brand-primary hover:bg-brand-light">
                    {{ $alternate['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    <!-- Gallery -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10 md:h-[520px]">
        <div class="md:col-span-2 relative rounded-2xl overflow-hidden h-72 md:h-full min-h-0 bg-gray-200 skeleton-shimmer">
            <img src="{{ $destination->media === '' ? $destination->imageUrl(1600) : $seo->imageUrl }}" @if (! $seo->isPlaceholderImage) property="schema:image" @endif class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300" alt="{{ $destination->name }}" onload="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')" onerror="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')">
            @include('partials.image-attribution', ['destination' => $destination])
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
                    <a href="{{ route('destinations', ['jenis' => $destination->primaryType()]) }}" class="text-[11px] font-bold text-brand-accent hover:underline whitespace-nowrap">See more</a>
                </div>
            @endif

        <div class="grid grid-cols-3 gap-3 md:grid-cols-1 md:grid-rows-3 md:gap-4 flex-1 min-h-0">
            @foreach ($related->take(3) as $nearby)
                <a href="{{ route('destination.detail', $nearby->id) }}" class="relative rounded-2xl overflow-hidden h-28 md:h-auto min-h-0 group bg-gray-200 skeleton-shimmer">
                    <img src="{{ $nearby->imageUrl(600) }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="{{ $nearby->name }}" loading="lazy" onload="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')" onerror="this.closest('.skeleton-shimmer')?.classList.remove('skeleton-shimmer')">
                    @include('partials.image-attribution', ['destination' => $nearby])
                    <div class="absolute bottom-0 left-0 w-full p-3 bg-gradient-to-t from-black/80 to-transparent text-white">
                        <p class="text-[10px] font-bold truncate">{{ $nearby->name }}</p>
                        <p class="text-[9px] text-gray-300 truncate">{{ $nearby->province() }} • {{ $nearby->primaryType() }}</p>
                    </div>
                </a>
            @endforeach

            @for ($index = $related->take(3)->count(); $index < 3; $index++)
                <div class="relative rounded-2xl overflow-hidden h-28 md:h-auto min-h-0 bg-gray-200">
                    <img src="{{ $placeholder }}" class="absolute inset-0 w-full h-full object-cover" alt="Placeholder for {{ $destination->primaryType() }} media">
                    <div class="absolute bottom-0 left-0 w-full p-3 bg-gradient-to-t from-black/80 to-transparent text-white">
                        <p class="text-[10px] font-bold truncate">{{ $destination->name }}</p>
                        <p class="text-[9px] text-gray-300">Additional media not recorded</p>
                    </div>
                </div>
            @endfor
        </div>
        </div>
    </div>

    @if ($attribution = $destination->imageAttribution())
        <p class="mb-8 text-xs text-gray-500">
            Photo by <a href="{{ $attribution['source_url'] }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-primary">{{ $attribution['artist'] }}</a>,
            licensed under <a href="{{ $attribution['license_url'] }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-primary">{{ $attribution['license'] }}</a> via Wikimedia Commons.
        </p>
    @endif

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
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Ontology ID</span><span class="font-bold text-gray-900 text-right">ID <span property="ws:idDestinasi">{{ $destination->id }}</span></span></div>
                    @if ($destination->openingHours !== '')
                        <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Opening hours</span><span class="font-bold text-gray-900 text-right" property="ws:keteranganJadwal">{{ $destination->openingHours }}</span></div>
                    @endif
                    @foreach ($destination->prices as $price)
                        @if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $price) === 1)
                            <meta property="ws:hargaMasuk" content="{{ $price }}" datatype="xsd:decimal">
                        @endif
                    @endforeach
                    <div class="flex justify-between gap-4"><span>Entry from</span><span class="font-bold text-gray-900 text-right">{{ $destination->priceLabel() }}</span></div>
                </div>

                <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" class="block text-center bg-brand-dark hover:bg-gray-800 text-white font-bold text-sm py-3 rounded-lg transition mb-3">
                    📍 Open in Google Maps
                </a>
                <a href="{{ route('destinations', ['jenis' => $destination->primaryType()]) }}" class="block text-center bg-brand-light border border-brand-primary/15 text-brand-dark font-bold text-sm py-3 rounded-lg hover:bg-gray-100 transition">
                    More {{ $destination->primaryType() }} destinations
                </a>

                <div class="flex justify-center text-[10px] text-gray-500 bg-gray-50 py-2 rounded-lg border border-gray-100 mt-4 text-center">
                    🛡️ Emergency assistance: <a href="tel:112" class="font-bold underline underline-offset-2 hover:text-brand-primary">National 112</a>
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
                    @if ($destination->hasCoordinates())
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Coordinates</p>
                            <p class="font-bold text-sm text-gray-900">{{ number_format($destination->latitude, 4) }}, {{ number_format($destination->longitude, 4) }}</p>
                            <p class="text-[10px] text-gray-500">Latitude, longitude</p>
                            <div property="schema:geo" typeof="schema:GeoCoordinates">
                                <meta property="schema:latitude" content="{{ json_encode($destination->latitude, JSON_PRESERVE_ZERO_FRACTION) }}" datatype="xsd:decimal">
                                <meta property="schema:longitude" content="{{ json_encode($destination->longitude, JSON_PRESERVE_ZERO_FRACTION) }}" datatype="xsd:decimal">
                            </div>
                        </div>
                    @endif
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Rating</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->ratingLabel() }}@if ($destination->rating !== null) / 5 @endif</p>
                        <p class="text-[10px] text-gray-500">Visitor average</p>
                    </div>
                </div>
            </div>

            <section class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm" aria-labelledby="wikidata-region-heading">
                <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2">External regional reference</p>
                <h2 id="wikidata-region-heading" class="text-2xl font-serif font-bold text-gray-900 mb-3">Wikidata: {{ $destination->location !== '' ? $destination->location : $destination->province() }}</h2>
                @if ($wikidataRegion)
                    @if ($wikidataRegion['is_stale'])
                        <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-xs text-amber-900">Showing a saved snapshot that may be outdated.</p>
                    @endif
                    <p class="text-sm text-gray-600 mb-5">Administrative reference only. This external information does not replace destination facts; only the region resource is linked to Wikidata.</p>
                    @if ($linkedData->region !== null && ! empty($wikidataRegion['wikidata_url']) && $wikidataRegion['wikidata_url'] !== '-')
                        <a about="{{ $linkedData->region->iri }}" rel="owl:sameAs" href="{{ $wikidataRegion['wikidata_url'] }}">
                            Wikidata identity: {{ $wikidataRegion['qid'] }}
                        </a>
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        @if ($wikidataRegion['image_url'])
                            <a href="{{ $wikidataRegion['wikidata_url'] }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl bg-gray-100">
                                <img src="{{ $wikidataRegion['image_url'] }}" alt="Wikimedia image for {{ $wikidataRegion['wikidata_label'] }}" loading="lazy" referrerpolicy="no-referrer" class="h-48 w-full object-cover">
                            </a>
                        @endif
                        <div class="space-y-3 text-sm text-gray-600">
                            <p>Wikidata place:
                                <a href="{{ $wikidataRegion['wikidata_url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-primary underline">
                                    {{ $wikidataRegion['wikidata_label'] }} ({{ $wikidataRegion['qid'] }})
                                </a>
                            </p>
                            @if ($wikidataRegion['latitude'] !== null && $wikidataRegion['longitude'] !== null)
                                <p>Regional coordinates: {{ number_format($wikidataRegion['latitude'], 5) }}, {{ number_format($wikidataRegion['longitude'], 5) }}</p>
                            @endif
                            @if ($wikidataRegion['population'])
                                <p>Population: {{ number_format((float) $wikidataRegion['population']['amount']) }}@if ($wikidataRegion['population']['as_of']) <span class="text-xs text-gray-500">(as of {{ $wikidataRegion['population']['as_of'] }})</span>@endif</p>
                            @endif
                            @if ($wikidataRegion['area'])
                                @php
                                    $areaValue = $wikidataRegion['area'];
                                    $areaUnitQid = $areaValue['unit_qid'] ?? null;
                                @endphp
                                <p>
                                    Area: {{ number_format((float) $areaValue['amount'], 2) }}
                                    @if ($areaUnitQid)
                                        <a href="https://www.wikidata.org/entity/{{ $areaUnitQid }}" target="_blank" rel="noopener noreferrer" class="underline">unit {{ $areaUnitQid }}</a>
                                    @endif
                                </p>
                            @endif
                            @if ($wikidataRegion['website_url'] ?? null)
                                <p>
                                    Official website:
                                    <a href="{{ $wikidataRegion['website_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">
                                        {{ $wikidataRegion['website_url'] }}
                                    </a>
                                </p>
                            @endif
                            <p class="border-t border-gray-100 pt-3 text-xs text-gray-500">
                                Source: Wikidata. Snapshot retrieved {{ date('Y-m-d', strtotime($wikidataRegion['snapshot_retrieved_at'])) }}.
                            </p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-600">No current Wikidata region snapshot is available. The destination data remains unchanged.</p>
                @endif
            </section>

            <!-- Visitor Information -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-1">Visitor Information</h2>
                    <p class="text-xs text-gray-500">Operational hours, ticketing, and official contact channels.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if ($destination->openingHours !== '')
                        <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                            <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">🕒 Operational Hours</p>
                            <p class="text-xs text-gray-700 font-bold mb-1">{{ $destination->openingHours }}</p>
                            <p class="text-[10px] text-gray-500">Schedules may change during public holidays or extreme weather.</p>
                        </div>
                    @endif

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

                    @if ($hasContact)
                        <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                            <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">📞 Official Contact</p>
                            <div class="space-y-1 text-xs text-gray-700">
                                @if ($destination->phone !== '')
                                    <p><a href="{{ $phoneHref }}" property="schema:telephone" content="{{ $destination->phone }}" class="font-bold hover:text-brand-primary">{{ $destination->phone }}</a></p>
                                @endif
                                @if ($destination->email !== '')
                                    <p><a href="mailto:{{ $destination->email }}" property="schema:email" content="{{ $destination->email }}" class="font-bold hover:text-brand-primary break-all">{{ $destination->email }}</a></p>
                                @endif
                                @if ($destination->instagram !== '')
                                    <p><a href="{{ $destination->instagram }}" property="ws:urlInstagram" content="{{ $destination->instagram }}" target="_blank" rel="noopener" class="font-bold hover:text-brand-primary break-all">Instagram profile</a></p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($destination->address !== '' || $destination->location !== '')
                        <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                            <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">📍 Address</p>
                            <p class="text-xs text-gray-700 font-bold mb-1">{{ $destination->address !== '' ? $destination->address : $destination->location }}</p>
                            @if ($destination->location !== '')
                                <p class="text-[10px] text-gray-500">{{ $destination->location }}</p>
                            @endif
                        </div>
                    @endif
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
                                        @include('partials.rdfa-resource-label', [
                                            'resource' => $linkedData->facilityForLabel($facility),
                                            'relation' => 'ws:menyediakanFasilitas',
                                            'label' => $facility,
                                            'class' => 'text-xs bg-white border border-brand-primary/15 text-gray-700 rounded-full px-3 py-1',
                                        ])
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
                                        @include('partials.rdfa-resource-label', [
                                            'resource' => $linkedData->activityForLabel($activity),
                                            'relation' => 'ws:menawarkanAktivitas',
                                            'label' => $activity,
                                            'class' => 'text-xs bg-white border border-brand-accent/15 text-gray-700 rounded-full px-3 py-1',
                                        ])
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
                        @if ($destination->address !== '')
                            <p class="text-xs text-gray-500" property="schema:address">{{ $destination->address }}</p>
                        @endif
                        @if ($linkedData->region !== null)
                            <p class="text-xs text-gray-500">
                                @if ($destination->address !== '')
                                    Wilayah:
                                @endif
                                @include('partials.rdfa-resource-label', [
                                    'resource' => $linkedData->region,
                                    'relation' => 'ws:berlokasiDi',
                                    'label' => $destination->location,
                                    'class' => '',
                                ])
                            </p>
                        @elseif ($destination->address === '' && $destination->location !== '')
                            <p class="text-xs text-gray-500">{{ $destination->location }}</p>
                        @endif
                    </div>
                    <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener" class="text-brand-accent hover:underline text-xs font-bold flex items-center gap-1 whitespace-nowrap">Open in Google Maps</a>
                </div>

                @if ($destination->hasCoordinates())
                    <div
                        class="rounded-xl overflow-hidden h-64 border border-gray-200 bg-gray-100 relative z-0"
                        id="detail-map"
                        data-latitude="{{ json_encode($destination->latitude) }}"
                        data-longitude="{{ json_encode($destination->longitude) }}"
                        data-name="{{ $destination->name }}"
                        data-location="{{ $destination->location }}"
                        aria-label="Map showing {{ $destination->name }}"
                    ></div>
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

            @if ($linkedData->neighbors !== [])
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h2 class="text-xl font-serif font-bold text-gray-900 mb-4">Destinasi terdekat</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($linkedData->neighbors as $neighbor)
                            <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700" rel="ws:dekatDengan" resource="{{ $neighbor->iri }}" typeof="{{ $neighbor->type }}">
                                @if ($neighbor->destinationId !== null)
                                    <a property="{{ $neighbor->labelProperty }}" href="{{ route('destination.detail', $neighbor->destinationId) }}" class="hover:text-brand-primary">{{ $neighbor->label }}</a>
                                @else
                                    <span property="{{ $neighbor->labelProperty }}">{{ $neighbor->label }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

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
        <a href="{{ route('destinations', ['jenis' => $otherTypes[0]]) }}" class="text-xs font-bold text-brand-accent hover:underline whitespace-nowrap">View category</a>
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