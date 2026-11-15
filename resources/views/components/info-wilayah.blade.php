@props([
    'region' => null,
    'regionName' => null,
    'regionResource' => null,
    'regionNeighbors' => [],
    'wikipediaUrl' => null,
    'wikivoyageUrl' => null,
    'headingId' => 'info-wilayah-heading',
])

@if (is_array($region))
    @php
        $allowedUrl = static function (mixed $value, array $hosts): ?string {
            if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                return null;
            }

            $parts = parse_url($value);
            if (! is_array($parts)
                || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || ! in_array(strtolower((string) ($parts['host'] ?? '')), $hosts, true)
                || isset($parts['user'])
                || isset($parts['pass'])) {
                return null;
            }

            return $value;
        };
        $imageUrl = $allowedUrl($region['image_url'] ?? null, ['commons.wikimedia.org', 'upload.wikimedia.org']);
        $imageUrl = is_string($imageUrl) && str_starts_with($imageUrl, 'https://') ? $imageUrl : null;
        $imagePageUrl = $allowedUrl($region['image_page_url'] ?? null, ['commons.wikimedia.org']);
        $imagePageUrl = is_string($imagePageUrl)
            && str_starts_with($imagePageUrl, 'https://')
            && str_starts_with((string) parse_url($imagePageUrl, PHP_URL_PATH), '/wiki/File:')
                ? $imagePageUrl
                : null;
        $websiteUrl = $allowedUrl($region['website_url'] ?? null, array_filter([
            strtolower((string) parse_url((string) ($region['website_url'] ?? ''), PHP_URL_HOST)),
        ]));
        $websiteDomain = is_string($websiteUrl) ? parse_url($websiteUrl, PHP_URL_HOST) : null;
        $wikipediaUrl = $region['wikipedia_url'] ?? $wikipediaUrl;
        $wikipediaUrl = $allowedUrl($wikipediaUrl, ['id.wikipedia.org']);
        $wikipediaUrl = is_string($wikipediaUrl)
            && str_starts_with((string) parse_url($wikipediaUrl, PHP_URL_PATH), '/wiki/')
                ? $wikipediaUrl
                : null;
        $wikivoyageUrl = $region['wikivoyage_url'] ?? $wikivoyageUrl;
        $wikivoyageUrl = $allowedUrl($wikivoyageUrl, ['id.wikivoyage.org', 'en.wikivoyage.org']);
        $wikivoyageUrl = is_string($wikivoyageUrl)
            && str_starts_with((string) parse_url($wikivoyageUrl, PHP_URL_PATH), '/wiki/')
                ? $wikivoyageUrl
                : null;
        $areaValue = null;
        $areaAmount = $region['area']['amount'] ?? null;
        $areaUnit = $region['area']['unit_qid'] ?? null;

        if (is_numeric($areaAmount) && is_finite((float) $areaAmount)) {
            if ($areaUnit === 'Q712226') {
                $areaValue = (float) $areaAmount;
            } elseif (($region['area']['normalized']['unit_qid'] ?? null) === 'Q25343'
                && is_numeric($region['area']['normalized']['amount'] ?? null)
                && is_finite((float) $region['area']['normalized']['amount'])) {
                $areaValue = (float) $region['area']['normalized']['amount'] / 1_000_000;
            }
        }

        $population = $region['population']['amount'] ?? null;
        $population = is_numeric($population) && is_finite((float) $population)
            ? (float) $population
            : null;
        $dataNamespace = config('ontology.namespaces.data');
        $regionResourceSuffix = is_string($dataNamespace) && is_string($regionResource)
            && str_starts_with($regionResource, $dataNamespace)
                ? substr($regionResource, strlen($dataNamespace))
                : '';
        $validRegionResource = preg_match('/^[A-Za-z0-9._~-]+$/', $regionResourceSuffix) === 1;
        $sameAsUrl = $allowedUrl($region['wikidata_url'] ?? null, ['www.wikidata.org']);
        $sameAsUrl = is_string($sameAsUrl)
            && preg_match('~^http://www\.wikidata\.org/entity/Q[1-9]\d*$~D', $sameAsUrl) === 1
                ? $sameAsUrl
                : null;
        $displayName = is_string($regionName) && trim($regionName) !== ''
            ? trim($regionName)
            : (is_string($region['wikidata_label'] ?? null) ? trim($region['wikidata_label']) : '');
        $fallbackImage = asset('images/placeholders/destinasi.svg');
    @endphp

    @if ($displayName !== '')
        <section
            class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm"
            aria-labelledby="{{ $headingId }}"
            prefix="owl: http://www.w3.org/2002/07/owl# ws: http://pariwisatasumut.org/ontology/v2# rdfs: http://www.w3.org/2000/01/rdf-schema#"
            @if ($validRegionResource) about="{{ $regionResource }}" typeof="ws:KabupatenKota" @endif
        >
            @if ($validRegionResource && $sameAsUrl)
                <span class="hidden" about="{{ $regionResource }}" rel="owl:sameAs" resource="{{ $sameAsUrl }}"></span>
            @endif

            <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2">Mengenal wilayah</p>
            <h2 id="{{ $headingId }}" class="text-2xl font-serif font-bold text-gray-900 mb-5">Tentang {{ $displayName }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                @if ($imageUrl)
                    <div>
                        @if ($imagePageUrl)
                            <a href="{{ $imagePageUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="block overflow-hidden rounded-xl bg-gray-100">
                        @else
                            <div class="overflow-hidden rounded-xl bg-gray-100">
                        @endif
                            <img
                                src="{{ $imageUrl }}"
                                alt="Pemandangan wilayah {{ $displayName }}"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                class="h-48 w-full object-cover"
                                onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                            >
                        @if ($imagePageUrl)
                            </a>
                        @else
                            </div>
                        @endif
                    </div>
                @endif

                <div class="space-y-3 text-sm text-gray-600">
                    @if ($population !== null)
                        <p>Populasi: {{ \App\Support\IndonesianFormat::number($population) }}</p>
                    @endif
                    @if ($areaValue !== null)
                        <p>Luas wilayah: {{ \App\Support\IndonesianFormat::number($areaValue, 2) }} km²</p>
                    @endif
                    @if (is_string($websiteUrl) && is_string($websiteDomain))
                        <p>
                            Situs resmi:
                            <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">{{ $websiteDomain }}</a>
                        </p>
                    @endif
                    @if ($wikipediaUrl)
                        <p><a href="{{ $wikipediaUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-brand-primary underline">Baca selengkapnya</a></p>
                    @endif
                    @if ($wikivoyageUrl)
                        <p><a href="{{ $wikivoyageUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-brand-primary underline">Panduan perjalanan</a></p>
                    @endif
                </div>
            </div>

            @if (is_array($regionNeighbors) && $regionNeighbors !== [])
                <div class="mt-6 border-t border-gray-100 pt-5">
                    <h3 class="text-sm font-bold text-gray-900 mb-3">Wilayah sekitar</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($regionNeighbors as $neighbor)
                            @if ($neighbor instanceof \App\Data\LinkedResource)
                                <span
                                    rel="ws:berbatasanDengan"
                                    resource="{{ $neighbor->iri }}"
                                    typeof="{{ $neighbor->type }}"
                                    class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700"
                                >
                                    <a
                                        href="{{ route('destinations', ['kabupaten' => $neighbor->label]) }}"
                                        property="{{ $neighbor->labelProperty }}"
                                        class="hover:text-brand-primary"
                                    >{{ $neighbor->label }}</a>
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif
@endif
