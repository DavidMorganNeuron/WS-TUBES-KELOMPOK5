<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;
use JsonException;

final class WikidataRegionCatalog
{
    public function __construct(private readonly string $path) {}

    /**
     * @return array{
     *     source: string,
     *     retrieved_at: string,
     *     query_url: string,
     *     verification: array<string, mixed>,
     *     regions: array<string, array{qid: string, type: string}>,
     *     boundaries: list<array{0: string, 1: string}>
     * }
     */
    public function load(): array
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException("Snapshot wilayah Wikidata tidak ditemukan: {$this->path}");
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new InvalidArgumentException("Snapshot wilayah Wikidata tidak dapat dibaca: {$this->path}");
        }

        try {
            $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Snapshot wilayah Wikidata bukan JSON yang valid.', previous: $exception);
        }

        if (! is_array($snapshot)
            || ! is_string($snapshot['source'] ?? null)
            || ! is_string($snapshot['retrieved_at'] ?? null)
            || ! is_string($snapshot['query_url'] ?? null)
            || ! is_array($snapshot['verification'] ?? null)
            || ! is_array($snapshot['regions'] ?? null)
            || ! is_array($snapshot['boundaries'] ?? null)
            || $snapshot['regions'] === []
            || ! array_is_list($snapshot['boundaries'])) {
            throw new InvalidArgumentException('Struktur snapshot wilayah Wikidata tidak valid.');
        }

        $verification = $snapshot['verification'];
        foreach (['country_qid', 'province_qid', 'required_parent'] as $key) {
            if (! is_string($verification[$key] ?? null)
                || preg_match('/^Q[0-9]+$/', $verification[$key]) !== 1) {
                throw new InvalidArgumentException("Snapshot wilayah tidak memiliki QID verifikasi {$key} yang valid.");
            }
        }

        $regions = [];
        $qids = [];
        foreach ($snapshot['regions'] as $name => $region) {
            if (! is_string($name) || trim($name) === ''
                || ! is_array($region)
                || ! is_string($region['qid'] ?? null)
                || preg_match('/^Q[0-9]+$/', $region['qid']) !== 1
                || ! is_string($region['type'] ?? null)
                || ! in_array($region['type'], ['Kabupaten', 'Kota'], true)
                || isset($qids[$region['qid']])) {
                throw new InvalidArgumentException('Entri wilayah atau QID duplikat pada snapshot tidak valid.');
            }

            $regions[$name] = ['qid' => $region['qid'], 'type' => $region['type']];
            $qids[$region['qid']] = true;
        }

        $boundaries = [];
        $seenBoundaries = [];
        foreach ($snapshot['boundaries'] as $boundary) {
            if (! is_array($boundary) || count($boundary) !== 2
                || ! array_is_list($boundary)
                || ! is_string($boundary[0] ?? null)
                || ! is_string($boundary[1] ?? null)
                || ! isset($qids[$boundary[0]], $qids[$boundary[1]])
                || $boundary[0] === $boundary[1]) {
                throw new InvalidArgumentException('Pasangan batas wilayah pada snapshot tidak valid.');
            }

            $pair = $boundary;
            sort($pair);
            $key = implode('|', $pair);
            if (isset($seenBoundaries[$key])) {
                throw new InvalidArgumentException("Pasangan batas wilayah duplikat pada snapshot: {$key}.");
            }

            $seenBoundaries[$key] = true;
            $boundaries[] = [$pair[0], $pair[1]];
        }

        return [
            'source' => $snapshot['source'],
            'retrieved_at' => $snapshot['retrieved_at'],
            'query_url' => $snapshot['query_url'],
            'verification' => $verification,
            'regions' => $regions,
            'boundaries' => $boundaries,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function qidsByRegionName(): array
    {
        $catalog = $this->load();
        $mappings = [];
        foreach ($catalog['regions'] as $name => $region) {
            $mappings[$name] = $region['qid'];
        }

        return $mappings;
    }
}
