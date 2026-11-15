@props([
    'destination' => null,
    'destinationName' => null,
    'destinationResource' => null,
    'headingId' => 'info-destinasi-heading',
])

@php
    $destination = is_array($destination) ? $destination : [];
    $safeUrl = static function (mixed $value, array $hosts, string $pathPrefix): ?string {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), $hosts, true)
            || ! str_starts_with((string) ($parts['path'] ?? ''), $pathPrefix)
            || isset($parts['user'], $parts['pass'], $parts['port'], $parts['query'], $parts['fragment'])) {
            return null;
        }

        return $value;
    };
    $imageUrl = $safeUrl($destination['image_url'] ?? null, ['commons.wikimedia.org', 'upload.wikimedia.org'], '/');
    $imagePageUrl = $safeUrl($destination['image_page_url'] ?? null, ['commons.wikimedia.org'], '/wiki/File:');
    $wikipediaUrl = $safeUrl($destination['wikipedia_url'] ?? null, ['id.wikipedia.org'], '/wiki/');
    $wikivoyageUrl = $safeUrl($destination['wikivoyage_url'] ?? null, ['id.wikivoyage.org', 'en.wikivoyage.org'], '/wiki/');
    $displayName = is_string($destinationName) ? trim($destinationName) : '';
    $aliases = [];
    foreach (['id', 'en'] as $language) {
        foreach (array_merge(
            is_array($destination['aliases'][$language] ?? null) ? $destination['aliases'][$language] : [],
            $language === 'en' && is_string($destination['labels']['en'] ?? null)
                ? [$destination['labels']['en']]
                : [],
        ) as $alias) {
            if (is_string($alias) && trim($alias) !== '' && mb_strtolower(trim($alias)) !== mb_strtolower($displayName)) {
                $aliases[mb_strtolower(trim($alias))] = trim($alias);
            }
        }
    }
    $elevation = $destination['elevation_m'] ?? null;
    $elevation = is_numeric($elevation) && is_finite((float) $elevation) && (float) $elevation >= 0
        ? (float) $elevation
        : null;
    $establishmentYear = $destination['establishment_year'] ?? null;
    $establishmentYear = is_int($establishmentYear) && $establishmentYear >= 1 && $establishmentYear <= 9999
        ? $establishmentYear
        : null;
    $heritageStatuses = is_array($destination['heritage_statuses'] ?? null)
        ? array_slice(array_filter($destination['heritage_statuses'], static fn (mixed $status): bool => is_array($status) && is_string($status['label'] ?? null) && trim($status['label']) !== ''), 0, 3)
        : [];
    $dataNamespace = config('ontology.namespaces.data');
    $resourceSuffix = is_string($dataNamespace) && is_string($destinationResource)
        && str_starts_with($destinationResource, $dataNamespace)
            ? substr($destinationResource, strlen($dataNamespace))
            : '';
    $validDestinationResource = preg_match('/^[A-Za-z0-9._~-]+$/', $resourceSuffix) === 1;
    $sameAsUrl = $destination['wikidata_url'] ?? null;
    $sameAsUrl = is_string($sameAsUrl)
        && preg_match('~^http://www\.wikidata\.org/entity/Q[1-9]\d*$~D', $sameAsUrl) === 1
            ? $sameAsUrl
            : null;
    $hasDetails = $imageUrl !== null || $wikipediaUrl !== null || $wikivoyageUrl !== null
        || $elevation !== null || $establishmentYear !== null || $aliases !== [] || $heritageStatuses !== [];
@endphp

@if ($validDestinationResource && $sameAsUrl)
    <span class="hidden" prefix="owl: http://www.w3.org/2002/07/owl#" about="{{ $destinationResource }}" rel="owl:sameAs" resource="{{ $sameAsUrl }}"></span>
@endif

@if (is_array($destination) && $hasDetails)
    <section
        class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm"
        aria-labelledby="{{ $headingId }}"
        prefix="owl: http://www.w3.org/2002/07/owl# ws: http://pariwisatasumut.org/ontology/v2#"
        @if ($validDestinationResource) about="{{ $destinationResource }}" typeof="ws:DestinasiWisata" @endif
    >
        <p class="text-xs font-bold text-brand-accent uppercase tracking-widest mb-2">Info destinasi</p>
        <h2 id="{{ $headingId }}" class="text-2xl font-serif font-bold text-gray-900 mb-5">Mengenal {{ $displayName }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            @if ($imageUrl)
                <div>
                    @if ($imagePageUrl)
                        <a href="{{ $imagePageUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="block overflow-hidden rounded-xl bg-gray-100">
                    @else
                        <div class="overflow-hidden rounded-xl bg-gray-100">
                    @endif
                        <img src="{{ $imageUrl }}" alt="Foto {{ $displayName }}" loading="lazy" referrerpolicy="no-referrer" class="h-48 w-full object-cover">
                    @if ($imagePageUrl)
                        </a>
                    @else
                        </div>
                    @endif
                </div>
            @endif

            <div class="space-y-3 text-sm text-gray-600">
                @if ($heritageStatuses !== [])
                    <p class="font-semibold text-gray-800">Status warisan</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($heritageStatuses as $status)
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">{{ trim($status['label']) }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($elevation !== null)
                    <p>Ketinggian: {{ \App\Support\IndonesianFormat::number($elevation, floor($elevation) === $elevation ? 0 : 1) }} mdpl</p>
                @endif
                @if ($establishmentYear !== null)
                    <p>Tahun berdiri: {{ \App\Support\IndonesianFormat::year($establishmentYear) }}</p>
                @endif
                @if ($aliases !== [])
                    <p>Dikenal juga sebagai: {{ implode(', ', array_values($aliases)) }}</p>
                @endif
                <div class="flex flex-wrap gap-3 pt-1">
                    @if ($wikipediaUrl)
                        <a href="{{ $wikipediaUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-brand-primary underline">Baca selengkapnya</a>
                    @endif
                    @if ($wikivoyageUrl)
                        <a href="{{ $wikivoyageUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-brand-primary underline">Panduan perjalanan</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
