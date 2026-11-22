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
    $dataNamespace = config('ontology.namespaces.data');
    $scheduleResource = is_string($dataNamespace)
        ? rtrim($dataNamespace, '/').'/jadwal-'.$destination->id
        : null;
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
        <a href="{{ route('home') }}" class="hover:text-brand-primary">🏠 Beranda</a> <span>›</span>
        <a href="{{ route('destinations') }}" class="hover:text-brand-primary">Destinasi</a> <span>›</span>
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
                <span class="bg-[#e8f5e9] text-[#0f5132] text-[10px] font-bold px-3 py-1 rounded-full border border-[#0f5132]/20">📍 Memiliki koordinat</span>
            @endif
            @if ($destination->media !== '')
                <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1">📷 Media ontologi</span>
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
                >Bagikan</button>
                <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener noreferrer" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm">📍 Google Maps</a>
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
                        <p class="text-[10px] font-bold text-brand-accent uppercase tracking-widest">Rekomendasi</p>
                        <h2 class="text-lg font-serif font-bold text-gray-900 leading-tight">Anda mungkin juga menyukai</h2>
                    </div>
                    <a href="{{ route('destinations', ['jenis' => $destination->primaryType()]) }}" class="text-[11px] font-bold text-brand-accent hover:underline whitespace-nowrap">Lihat lainnya</a>
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
                    <img src="{{ $placeholder }}" class="absolute inset-0 w-full h-full object-cover" alt="Gambar pengganti media {{ $destination->primaryType() }}">
                    <div class="absolute bottom-0 left-0 w-full p-3 bg-gradient-to-t from-black/80 to-transparent text-white">
                        <p class="text-[10px] font-bold truncate">{{ $destination->name }}</p>
                        <p class="text-[9px] text-gray-300">Media tambahan belum tercatat</p>
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

        <!-- Sidebar (ringkasan pengunjung paling atas) -->
        <aside class="space-y-6 lg:order-2 lg:sticky lg:top-24 min-w-0">
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                <div class="flex justify-between items-start mb-4 gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Ringkasan pengunjung</p>
                        <h3 class="text-xl font-serif font-bold text-gray-900 leading-snug">{{ $destination->name }}</h3>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-[10px] text-gray-400">Penilaian</p>
                        <p class="font-bold text-brand-primary">⭐ {{ $destination->ratingLabel() }}</p>
                    </div>
                </div>

                <div class="space-y-2 text-xs text-gray-600 mb-5">
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Kategori</span><span class="font-bold text-gray-900 text-right">{{ implode(', ', $destination->types) }}</span></div>
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>Provinsi</span><span class="font-bold text-gray-900 text-right">{{ $destination->province() }}</span></div>
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2"><span>ID ontologi</span><span class="font-bold text-gray-900 text-right">ID <span property="ws:idDestinasi">{{ $destination->id }}</span></span></div>
                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2">
                        <span class="shrink-0">Jam operasional</span>
                        <span class="min-w-0 max-w-[65%] break-words whitespace-normal text-right font-bold text-gray-900">{{ $destination->openingHoursLabel() }}</span>
                    </div>
                    @foreach ($destination->prices as $price)
                        @if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $price) === 1)
                            <meta property="ws:hargaMasuk" content="{{ $price }}" datatype="xsd:decimal">
                        @endif
                    @endforeach
                    <div class="flex justify-between gap-4"><span>Tiket mulai dari</span><span class="font-bold text-gray-900 text-right">{{ $destination->priceLabel() }}</span></div>
                </div>

                <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener noreferrer" class="block text-center bg-brand-dark hover:bg-gray-800 text-white font-bold text-sm py-3 rounded-lg transition mb-3">
                    📍 Buka di Google Maps
                </a>
                <a href="{{ route('destinations', ['jenis' => $destination->primaryType()]) }}" class="block text-center bg-brand-light border border-brand-primary/15 text-brand-dark font-bold text-sm py-3 rounded-lg hover:bg-gray-100 transition">
                    More {{ $destination->primaryType() }} destinations
                </a>

                <div class="flex justify-center text-[10px] text-gray-500 bg-gray-50 py-2 rounded-lg border border-gray-100 mt-4 text-center">
                    🛡️ Bantuan darurat: <a href="tel:112" class="font-bold underline underline-offset-2 hover:text-brand-primary">Nomor 112 nasional</a>
                </div>
            </div>
        </aside>

        <!-- Main Column -->
        <div class="lg:col-span-2 lg:order-1 space-y-6 min-w-0">

            <!-- About -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2 flex items-center gap-2">📖 Kisah destinasi</p>
                <h2 class="text-3xl font-serif font-bold text-gray-900 mb-4">Tentang {{ $destination->name }}</h2>

                <div class="text-sm text-gray-600 space-y-4 leading-relaxed">
                    @forelse ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p>{{ $destination->shortDescription !== '' ? $destination->shortDescription : 'Deskripsi lengkap destinasi ini belum tercatat dalam ontologi.' }}</p>
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
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Provinsi</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->province() }}</p>
                        <p class="text-[10px] text-gray-500">Wilayah dalam ontologi</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Kategori</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->primaryType() }}</p>
                        <p class="text-[10px] text-gray-500">{{ \App\Support\IndonesianFormat::number(count($destination->types)) }} jenis tercatat</p>
                    </div>
                    @if ($destination->hasCoordinates())
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Koordinat</p>
                            <p class="font-bold text-sm text-gray-900">{{ \App\Support\IndonesianFormat::number($destination->latitude, 4) }}, {{ \App\Support\IndonesianFormat::number($destination->longitude, 4) }}</p>
                            <p class="text-[10px] text-gray-500">Garis lintang, garis bujur</p>
                            <div property="schema:geo" typeof="schema:GeoCoordinates">
                                <meta property="schema:latitude" content="{{ json_encode($destination->latitude, JSON_PRESERVE_ZERO_FRACTION) }}" datatype="xsd:decimal">
                                <meta property="schema:longitude" content="{{ json_encode($destination->longitude, JSON_PRESERVE_ZERO_FRACTION) }}" datatype="xsd:decimal">
                            </div>
                        </div>
                    @endif
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Penilaian</p>
                        <p class="font-bold text-sm text-gray-900">{{ $destination->ratingLabel() }}@if ($destination->rating !== null) / 5 @endif</p>
                        <p class="text-[10px] text-gray-500">Rata-rata pengunjung</p>
                    </div>
                </div>
            </div>

            <x-info-destinasi
                :destination="$wikidataDestination"
                :destination-name="$destination->name"
                :destination-resource="$linkedData->iri"
            />

            <x-info-wilayah
                :region="$wikidataRegion"
                :region-resource="$linkedData->region?->iri"
                :region-name="$destination->location !== '' ? $destination->location : $destination->province()"
                :region-neighbors="$linkedData->regionNeighbors"
            />

            <!-- Visitor Information -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-1">Informasi pengunjung</h2>
                    <p class="text-xs text-gray-500">Jam operasional, tiket masuk, dan kanal kontak resmi.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-100 bg-gray-50 rounded-xl p-4 min-w-0">
                        <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">🕒 Jam operasional</p>
                        <p class="text-xs text-gray-700 font-bold mb-1 break-words whitespace-normal">
                            @if ($destination->openingHours !== '' && is_string($scheduleResource))
                                <span rel="ws:memilikiJadwal" resource="{{ $scheduleResource }}" typeof="ws:JadwalOperasional"><span property="ws:keteranganJadwal">{{ $destination->openingHours }}</span></span>
                            @else
                                {{ $destination->openingHoursLabel() }}
                            @endif
                        </p>
                        @if ($destination->openingHours !== '')
                            <p class="text-[10px] text-gray-500">Jadwal dapat berubah saat hari libur atau perubahan operasional.</p>
                        @endif
                    </div>

                    <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                        <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">🎟️ Harga tiket</p>
                        @if ($destination->priceLabels() !== [])
                            <ul class="space-y-1">
                                @foreach ($destination->priceLabels() as $price)
                                    <li class="text-xs text-gray-700 font-bold">{{ $price }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-xs text-gray-700 font-bold mb-1">Informasi tiket belum tercatat.</p>
                        @endif
                        <p class="text-[10px] text-gray-500 mt-1">Biaya dikelola oleh pengelola destinasi.</p>
                    </div>

                    @if ($hasContact)
                        <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                            <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">📞 Kontak resmi</p>
                            <div class="space-y-1 text-xs text-gray-700">
                                @if ($destination->phone !== '')
                                    <p><a href="{{ $phoneHref }}" property="schema:telephone" content="{{ $destination->phone }}" class="font-bold hover:text-brand-primary">{{ $destination->phone }}</a></p>
                                @endif
                                @if ($destination->email !== '')
                                    <p><a href="mailto:{{ $destination->email }}" property="schema:email" content="{{ $destination->email }}" class="font-bold hover:text-brand-primary break-all">{{ $destination->email }}</a></p>
                                @endif
                                @if ($destination->instagram !== '')
                                    <p><a href="{{ $destination->instagram }}" property="ws:urlInstagram" content="{{ $destination->instagram }}" target="_blank" rel="noopener" class="font-bold hover:text-brand-primary break-all">Profil Instagram</a></p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($destination->address !== '' || $destination->location !== '')
                        <div class="border border-gray-100 bg-gray-50 rounded-xl p-4">
                            <p class="font-bold text-sm text-gray-900 mb-1 flex items-center gap-2">📍 Alamat</p>
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
                        <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2 flex items-center gap-2">🌿 Fasilitas & pengalaman</p>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">Fasilitas yang tersedia dan aktivitas</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-brand-light/60 p-6 rounded-2xl">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2 mb-4"><span class="w-2 h-2 rounded-full bg-brand-primary"></span> Fasilitas tersedia</h3>
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
                                <p class="text-xs text-gray-500">Fasilitas belum tercatat.</p>
                            @endif
                        </div>

                        <div class="bg-orange-50/60 p-6 rounded-2xl">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2 mb-4"><span class="w-2 h-2 rounded-full bg-brand-accent"></span> Aktivitas wisata</h3>
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
                                <p class="text-xs text-gray-500">Aktivitas belum tercatat.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Location -->
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <div class="flex justify-between items-end gap-4 mb-4">
                    <div class="min-w-0">
                        <h2 class="text-2xl font-serif font-bold text-gray-900 mb-1">Lokasi &amp; akses</h2>
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
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $destination->mapsUrl() }}" target="_blank" rel="noopener noreferrer" class="text-brand-accent hover:underline text-xs font-bold flex items-center gap-1 whitespace-nowrap">Buka di Google Maps</a>
                        <a href="{{ $destination->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="text-brand-accent hover:underline text-xs font-bold flex items-center gap-1 whitespace-nowrap">Petunjuk arah</a>
                    </div>
                </div>

                @if ($destination->hasCoordinates())
                    <div
                        class="rounded-xl overflow-hidden h-64 border border-gray-200 bg-gray-100 relative z-0"
                        id="detail-map"
                        data-id="{{ $destination->id }}"
                        data-latitude="{{ json_encode($destination->latitude) }}"
                        data-longitude="{{ json_encode($destination->longitude) }}"
                        data-name="{{ $destination->name }}"
                        data-location="{{ $destination->location }}"
                        data-detail-url="{{ route('destination.detail', $destination->id) }}"
                        data-google-maps-url="{{ $destination->mapsUrl() }}"
                        data-directions-url="{{ $destination->directionsUrl() }}"
                        aria-label="Peta lokasi {{ $destination->name }}"
                    ></div>
                @else
                    <div class="rounded-xl overflow-hidden h-64 border border-gray-200 bg-gray-100 flex items-center justify-center text-sm text-gray-500">
                        Koordinat destinasi ini belum tercatat.
                    </div>
                @endif

                @if ($destination->directions !== '')
                    <div class="mt-4 bg-gray-50 border border-gray-100 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-900 mb-1">🧭 Petunjuk arah</p>
                        <p class="text-xs text-gray-600 leading-relaxed">{{ $destination->directions }}</p>
                    </div>
                @endif
            </div>

            @if ($nearbyDestinations->isNotEmpty())
                @php
                    $neighborResources = collect($linkedData->neighbors)->keyBy('destinationId');
                @endphp
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h2 class="text-xl font-serif font-bold text-gray-900 mb-4">Destinasi terdekat</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($nearbyDestinations as $item)
                            @php
                                $neighbor = $neighborResources->get($item->id);
                            @endphp
                            @if ($neighbor !== null)
                                <x-destination-card
                                    :destination="$item"
                                    :show-description="false"
                                    :rdfa-resource="$neighbor->iri"
                                    :rdfa-type="$neighbor->type"
                                    rdfa-relation="ws:dekatDengan"
                                    :rdfa-label-property="$neighbor->labelProperty"
                                />
                            @endif
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
            <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-1">Jelajahi lebih banyak</p>
            <h2 class="text-2xl font-serif font-bold text-gray-900">Temukan pengalaman lainnya</h2>
            <p class="text-xs text-gray-500 mt-1">Selain {{ implode(', ', $destination->types) }}: coba juga {{ implode(', ', $otherTypes) }}.</p>
        </div>
        <a href="{{ route('destinations', ['jenis' => $otherTypes[0]]) }}" class="text-xs font-bold text-brand-accent hover:underline whitespace-nowrap">Lihat kategori</a>
    </div>

    @if ($crossCategory->isEmpty())
        <p class="text-sm text-gray-500">Tidak ada destinasi dari kategori lain dalam ontologi.</p>
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

@push('scripts')
    <script>
        window.WayFinderMapConfig = {
            googleMapsKey: @json(config('services.google_maps.key')),
        };
    </script>
@endpush
