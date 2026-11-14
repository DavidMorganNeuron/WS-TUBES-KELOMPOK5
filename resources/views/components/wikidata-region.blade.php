@props([
    'region' => null,
    'regionResource' => null,
    'title' => 'Referensi Wikidata',
    'headingId' => 'wikidata-region-heading',
])

<section class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm" aria-labelledby="{{ $headingId }}">
    <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2">Referensi wilayah eksternal</p>
    <h2 id="{{ $headingId }}" class="text-2xl font-serif font-bold text-gray-900 mb-3">{{ $title }}</h2>
    @if ($region)
        @if ($region['is_stale'] ?? false)
            <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-xs text-amber-900" role="status">Snapshot tersimpan ini mungkin sudah kedaluwarsa.</p>
        @endif
        <p class="text-sm text-gray-600 mb-5">Data ini hanya menjadi referensi administratif dan tidak menggantikan fakta destinasi. Hanya resource wilayah yang ditautkan ke Wikidata.</p>
        @if (is_string($regionResource) && $regionResource !== '' && ! empty($region['wikidata_url']))
            <p class="mb-4">
                <a about="{{ $regionResource }}" rel="owl:sameAs" href="{{ $region['wikidata_url'] }}" class="font-semibold text-brand-primary underline">
                    Identitas Wikidata: {{ $region['qid'] }}
                </a>
            </p>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            @if ($region['image_url'] ?? null)
                <div>
                    <a href="{{ $region['wikidata_url'] }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl bg-gray-100">
                        <img src="{{ $region['image_url'] }}" alt="Gambar wilayah {{ $region['wikidata_label'] }} dari Wikidata" loading="lazy" referrerpolicy="no-referrer" class="h-48 w-full object-cover">
                    </a>
                    <p class="mt-2 text-xs text-gray-500">
                        Sumber gambar:
                        <a href="{{ $region['image_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">Wikimedia Commons melalui Wikidata</a>.
                    </p>
                </div>
            @endif
            <div class="space-y-3 text-sm text-gray-600">
                <p>
                    Wilayah Wikidata:
                    <a href="{{ $region['wikidata_url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-primary underline">
                        {{ $region['wikidata_label'] }} ({{ $region['qid'] }})
                    </a>
                </p>
                @if (($region['latitude'] ?? null) !== null && ($region['longitude'] ?? null) !== null)
                    <p>
                        Koordinat wilayah:
                        {{ \App\Support\IndonesianFormat::number((float) $region['latitude'], 5) }},
                        {{ \App\Support\IndonesianFormat::number((float) $region['longitude'], 5) }}
                    </p>
                @endif
                @if ($region['population'] ?? null)
                    <p>
                        Populasi: {{ \App\Support\IndonesianFormat::number((float) $region['population']['amount']) }}
                        @if ($region['population']['as_of'] ?? null)
                            <span class="text-xs text-gray-500">(per {{ \App\Support\IndonesianFormat::date(\Illuminate\Support\Carbon::parse($region['population']['as_of'])) }})</span>
                        @endif
                    </p>
                @endif
                @if ($region['area'] ?? null)
                    @php
                        $areaUnitQid = $region['area']['unit_qid'] ?? null;
                    @endphp
                    <p>
                        Luas wilayah: {{ \App\Support\IndonesianFormat::number((float) $region['area']['amount'], 2) }}
                        @if ($areaUnitQid)
                            <a href="https://www.wikidata.org/entity/{{ $areaUnitQid }}" target="_blank" rel="noopener noreferrer" class="underline">satuan {{ $areaUnitQid }}</a>
                        @endif
                    </p>
                @endif
                @if ($region['website_url'] ?? null)
                    <p>
                        Situs resmi:
                        <a href="{{ $region['website_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">
                            {{ $region['website_url'] }}
                        </a>
                    </p>
                @endif
                <p class="border-t border-gray-100 pt-3 text-xs text-gray-500">
                    Sumber data: <a href="https://query.wikidata.org/sparql" target="_blank" rel="noopener noreferrer" class="underline">Wikidata Query Service</a>.
                    Snapshot diambil {{ \App\Support\IndonesianFormat::date(\Illuminate\Support\Carbon::parse($region['snapshot_retrieved_at'])) }}.
                </p>
            </div>
        </div>
    @else
        <p class="text-sm text-gray-600">Snapshot wilayah Wikidata belum tersedia. Data destinasi tidak berubah.</p>
    @endif
</section>
