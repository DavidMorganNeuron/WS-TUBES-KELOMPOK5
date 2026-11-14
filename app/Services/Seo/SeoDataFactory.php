<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Data\Destination;
use App\Data\SeoData;
use Illuminate\Http\Request;

final class SeoDataFactory
{
    public function default(): SeoData
    {
        return $this->build(
            title: 'WayFinder — Destinasi Wisata Sumatera Utara',
            description: 'Jelajahi destinasi wisata Sumatera Utara melalui data berbasis ontologi.',
            canonicalUrl: route('home'),
        );
    }

    public function home(): SeoData
    {
        return $this->build(
            title: 'WayFinder — Destinasi Wisata Sumatera Utara',
            description: 'Jelajahi destinasi wisata Sumatera Utara melalui pencarian semantik, peta interaktif, dan informasi destinasi.',
            canonicalUrl: route('home'),
        );
    }

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, sort?: string}  $filters
     */
    public function destinations(Request $request, array $filters): SeoData
    {
        $canonicalParameters = [];
        foreach (['q', 'jenis', 'kabupaten'] as $parameter) {
            $value = $filters[$parameter] ?? '';
            if ($value !== '') {
                $canonicalParameters[$parameter] = $value;
            }
        }

        $sort = $filters['sort'] ?? 'name';
        if ($sort !== '' && $sort !== 'name') {
            $canonicalParameters['sort'] = $sort;
        }

        $page = $request->query('page');
        $pageNumber = filter_var($page, FILTER_VALIDATE_INT);
        if ($pageNumber !== false && $pageNumber > 1) {
            $canonicalParameters['page'] = $pageNumber;
        }

        $query = http_build_query($canonicalParameters, '', '&', PHP_QUERY_RFC3986);
        $canonicalUrl = route('destinations').($query === '' ? '' : '?'.$query);
        $hasFilters = ($filters['q'] ?? '') !== ''
            || ($filters['jenis'] ?? '') !== ''
            || ($filters['kabupaten'] ?? '') !== ''
            || $sort !== 'name';

        return $this->build(
            title: 'Jelajahi Destinasi — WayFinder',
            description: 'Temukan dan bandingkan destinasi wisata di Sumatera Utara berdasarkan kategori, lokasi, dan informasi kunjungan.',
            canonicalUrl: $canonicalUrl,
            noindex: $hasFilters || ($page !== null && $page !== ''),
        );
    }

    public function map(): SeoData
    {
        return $this->build(
            title: 'Peta Destinasi Wisata — WayFinder',
            description: 'Jelajahi lokasi destinasi wisata Sumatera Utara pada peta interaktif WayFinder.',
            canonicalUrl: route('map'),
        );
    }

    public function destination(Destination $destination): SeoData
    {
        $formats = config('ontology.rdf_export.formats', []);
        $formatTitles = [
            'ttl' => 'Turtle',
            'rdf' => 'RDF/XML',
            'jsonld' => 'JSON-LD',
        ];
        $alternates = [];
        foreach ($formatTitles as $format => $title) {
            $formatConfig = is_array($formats) ? ($formats[$format] ?? null) : null;
            if (! is_array($formatConfig) || ! is_string($formatConfig['media_type'] ?? null)) {
                continue;
            }

            $alternates[] = [
                'type' => $formatConfig['media_type'],
                'url' => route('destination.rdf', ['id' => $destination->id, 'format' => $format]),
                'title' => 'Data RDF '.$title,
                'label' => $title,
            ];
        }

        $description = $destination->shortDescription !== ''
            ? $destination->shortDescription
            : $destination->fullDescription;

        return $this->build(
            title: $destination->name.' — WayFinder',
            description: $description !== ''
                ? $description
                : 'Informasi destinasi wisata '.$destination->name.' di Sumatera Utara.',
            canonicalUrl: route('destination.detail', $destination->id),
            imageSource: $destination->media,
            imageAlt: 'Foto destinasi '.$destination->name,
            ogType: 'website',
            latitude: $destination->hasCoordinates() ? $destination->latitude : null,
            longitude: $destination->hasCoordinates() ? $destination->longitude : null,
            alternates: $alternates,
        );
    }

    /**
     * @param  list<array{type: string, url: string, title: string, label: string}>  $alternates
     */
    private function build(
        string $title,
        string $description,
        string $canonicalUrl,
        string $imageSource = '',
        string $imageAlt = 'Pratinjau WayFinder',
        string $ogType = 'website',
        ?float $latitude = null,
        ?float $longitude = null,
        bool $noindex = false,
        array $alternates = [],
    ): SeoData {
        $placeholder = (string) config('ontology.og_image_placeholder');
        $isPlaceholder = ! $this->isValidImageUrl($imageSource);

        return new SeoData(
            title: $this->cleanText($title, 200),
            description: $this->cleanText($description, 180),
            canonicalUrl: $canonicalUrl,
            imageUrl: $isPlaceholder ? asset($placeholder) : $imageSource,
            imageAlt: $this->cleanText($imageAlt, 200),
            ogType: $ogType,
            latitude: $latitude,
            longitude: $longitude,
            imageWidth: $isPlaceholder ? 1200 : null,
            imageHeight: $isPlaceholder ? 630 : null,
            noindex: $noindex,
            isPlaceholderImage: $isPlaceholder,
            rdfaPrefixes: $this->rdfaPrefixes(),
            alternates: $alternates,
        );
    }

    private function isValidImageUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private function cleanText(string $value, int $limit): string
    {
        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[\p{C}\s]+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        $shortened = mb_substr($text, 0, $limit, 'UTF-8');
        $lastSpace = mb_strrpos($shortened, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > (int) ($limit * 0.65)) {
            $shortened = mb_substr($shortened, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($shortened).'...';
    }

    private function rdfaPrefixes(): string
    {
        $namespaces = config('ontology.namespaces', []);
        $prefixes = [];

        foreach (['schema', 'ws', 'data', 'rdfs', 'xsd', 'owl'] as $prefix) {
            $namespace = is_array($namespaces) ? ($namespaces[$prefix] ?? null) : null;
            if (is_string($namespace)) {
                $prefixes[] = $prefix.': '.$namespace;
            }
        }

        return implode(' ', $prefixes);
    }
}
