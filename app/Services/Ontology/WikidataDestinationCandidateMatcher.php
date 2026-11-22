<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use RuntimeException;

final class WikidataDestinationCandidateMatcher
{
    /**
     * @param  list<array<string, string>>  $sourceRows
     * @param  list<array<string, array{type: string, value: string}>>  $candidateRows
     * @param  list<array<string, array{type: string, value: string}>>  $nameRows
     * @return list<array{
     *     id: string,
     *     local_name: string,
     *     qid: string,
     *     label: string,
     *     class: string,
     *     distance_km: float,
     *     score: float,
     *     name_similarity: float,
     *     status: string,
     *     reason: string
     * }>
     */
    public function match(array $sourceRows, array $candidateRows, array $nameRows): array
    {
        $candidates = $this->candidates($candidateRows);
        $names = $this->names($nameRows);
        $destinationCandidates = [];

        foreach ($sourceRows as $sourceRow) {
            $id = trim($sourceRow['idDestinasi'] ?? '');
            $localName = trim($sourceRow['namaDestinasi'] ?? '');
            $latitude = $this->decimal($sourceRow['lintang'] ?? null);
            $longitude = $this->decimal($sourceRow['bujur'] ?? null);
            if ($id === '' || $localName === '' || $latitude === null || $longitude === null) {
                throw new RuntimeException('Sumber lokal memuat destinasi tanpa ID, nama, atau koordinat valid.');
            }

            $localNames = [$localName, ...$this->alternativeNames($sourceRow['namaAlternatif'] ?? '')];
            $matches = [];
            foreach ($candidates as $qid => $candidate) {
                $candidateNames = $names[$qid]['values'] ?? [];
                if ($candidateNames === []) {
                    continue;
                }

                $distance = min(array_map(
                    fn (array $coordinate): float => $this->distanceKm(
                        $latitude,
                        $longitude,
                        $coordinate['latitude'],
                        $coordinate['longitude'],
                    ),
                    $candidate['coordinates'],
                ));
                if ($distance > 20) {
                    continue;
                }

                $similarity = $this->bestNameSimilarity($localNames, $candidateNames);
                if ($similarity < 60) {
                    continue;
                }

                $proximity = 100 * max(0, 1 - ($distance / 20));
                $score = (0.75 * $similarity) + (0.25 * $proximity);
                $matches[] = [
                    'qid' => $qid,
                    'label' => $names[$qid]['display_label'],
                    'class' => implode('; ', $candidate['classes']),
                    'distance_km' => round($distance, 2),
                    'score' => round($score, 1),
                    'name_similarity' => round($similarity, 1),
                    'exact_name' => $this->hasExactName($localNames, $candidateNames),
                ];
            }

            usort($matches, static fn (array $left, array $right): int => [
                -$left['score'],
                -$left['name_similarity'],
                $left['distance_km'],
                $left['qid'],
            ] <=> [
                -$right['score'],
                -$right['name_similarity'],
                $right['distance_km'],
                $right['qid'],
            ]);

            $strongMatches = array_values(array_filter(
                $matches,
                static fn (array $match): bool => $match['name_similarity'] >= 90,
            ));
            $selected = $strongMatches === [] ? array_slice($matches, 0, 1) : array_slice($strongMatches, 0, 5);
            foreach ($selected as $match) {
                $destinationCandidates[] = [
                    'id' => $id,
                    'local_name' => $localName,
                    ...$match,
                    'status' => 'AMBIGU',
                    'reason' => '',
                ];
            }
        }

        $autoEligibleByDestination = [];
        $autoEligibleByQid = [];
        foreach ($destinationCandidates as $index => $candidate) {
            if ($candidate['exact_name'] && $candidate['distance_km'] <= 0.5
                && $candidate['name_similarity'] === 100.0) {
                $autoEligibleByDestination[$candidate['id']][] = $index;
                $autoEligibleByQid[$candidate['qid']][] = $index;
            }
        }

        foreach ($destinationCandidates as $index => &$candidate) {
            if ($candidate['name_similarity'] < 90) {
                $candidate['status'] = 'DITOLAK';
                $candidate['reason'] = 'Kemiripan nama kurang dari 90%; jarak saja tidak cukup untuk menyetujui identitas.';
            } elseif (isset($autoEligibleByDestination[$candidate['id']])
                && count($autoEligibleByDestination[$candidate['id']]) === 1
                && isset($autoEligibleByQid[$candidate['qid']])
                && count($autoEligibleByQid[$candidate['qid']]) === 1
                && $autoEligibleByDestination[$candidate['id']][0] === $index) {
                $candidate['status'] = 'AUTO-SETUJU';
                $candidate['reason'] = 'Nama ternormalisasi sama persis, jarak paling banyak 0,5 km, dan tautan satu-ke-satu.';
            } else {
                $candidate['status'] = 'AMBIGU';
                $candidate['reason'] = $candidate['distance_km'] > 0.5
                    ? 'Nama sangat mirip, tetapi jarak melebihi ambang persetujuan otomatis 0,5 km.'
                    : 'Ada kandidat lain atau QID ini berpotensi cocok dengan lebih dari satu destinasi.';
            }

            unset($candidate['exact_name']);
        }
        unset($candidate);

        usort($destinationCandidates, static fn (array $left, array $right): int => [
            $left['id'],
            -$left['score'],
            $left['distance_km'],
            $left['qid'],
        ] <=> [
            $right['id'],
            -$right['score'],
            $right['distance_km'],
            $right['qid'],
        ]);

        return $destinationCandidates;
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array{coordinates: list<array{latitude: float, longitude: float}>, classes: list<string>}>
     */
    private function candidates(array $rows): array
    {
        if (count($rows) >= 5000) {
            throw new RuntimeException('Penemuan kandidat mencapai batas 5.000 baris; hasil mungkin terpotong dan tidak dapat disetujui.');
        }

        $candidates = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->binding($row, 'candidate'));
            $classQid = $this->entityQid($this->binding($row, 'class'));
            $classLabel = $this->textBinding($row, 'classLabel');
            $coordinate = $this->coordinate($this->textBinding($row, 'location'));
            if ($qid === null || $classQid === null || $classLabel === null || $coordinate === null
                || ! in_array($this->textBinding($row, 'tourismClassLabel'), ['tourist attraction'], true)) {
                throw new RuntimeException('Baris penemuan live tidak cocok dengan kelas wisata yang sudah diverifikasi.');
            }

            $coordinateKey = $coordinate['latitude'].'|'.$coordinate['longitude'];
            $class = $classLabel.' ('.$classQid.')';
            $candidates[$qid] ??= ['coordinates' => [], 'classes' => []];
            $candidates[$qid]['coordinates'][$coordinateKey] = $coordinate;
            $candidates[$qid]['classes'][$class] = $class;
        }
        foreach ($candidates as &$candidate) {
            $candidate['coordinates'] = array_values($candidate['coordinates']);
            $candidate['classes'] = array_values($candidate['classes']);
            sort($candidate['classes'], SORT_STRING);
        }
        unset($candidate);
        ksort($candidates, SORT_STRING);

        return $candidates;
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array{values: list<string>, display_label: string}>
     */
    private function names(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->binding($row, 'item'));
            $kind = $this->textBinding($row, 'kind');
            $language = $this->textBinding($row, 'language');
            $value = $this->textBinding($row, 'value');
            if ($qid === null || ! in_array($kind, ['label', 'alias'], true)
                || ! in_array($language, ['id', 'en'], true) || $value === null
                || trim($value) === '' || mb_strlen($value) > 500) {
                throw new RuntimeException('Label atau alias kandidat live tidak valid.');
            }

            $names[$qid][$kind][$language][$value] = $value;
        }

        foreach ($names as $qid => &$entry) {
            foreach (['id', 'en'] as $language) {
                foreach (['label', 'alias'] as $kind) {
                    $entry[$kind][$language] = array_values($entry[$kind][$language] ?? []);
                    sort($entry[$kind][$language], SORT_STRING);
                }
            }
            $labels = [...$entry['label']['id'], ...$entry['label']['en']];
            if ($labels === []) {
                unset($names[$qid]);

                continue;
            }
            $entry['display_label'] = $entry['label']['id'][0] ?? $entry['label']['en'][0];
            $entry['values'] = array_values(array_unique([
                ...$entry['label']['id'],
                ...$entry['label']['en'],
                ...$entry['alias']['id'],
                ...$entry['alias']['en'],
            ]));
            sort($entry['values'], SORT_STRING);
        }
        unset($entry);

        return $names;
    }

    /**
     * @param  list<string>  $localNames
     * @param  list<string>  $candidateNames
     */
    private function bestNameSimilarity(array $localNames, array $candidateNames): float
    {
        $best = 0.0;
        foreach ($localNames as $localName) {
            $normalizedLocal = $this->normalizeName($localName);
            if ($normalizedLocal === '') {
                continue;
            }
            foreach ($candidateNames as $candidateName) {
                $normalizedCandidate = $this->normalizeName($candidateName);
                if ($normalizedCandidate === '') {
                    continue;
                }
                if ($normalizedLocal === $normalizedCandidate) {
                    return 100.0;
                }

                similar_text($normalizedLocal, $normalizedCandidate, $similarity);
                $best = max($best, $similarity);
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $localNames
     * @param  list<string>  $candidateNames
     */
    private function hasExactName(array $localNames, array $candidateNames): bool
    {
        $normalizedCandidateNames = array_map($this->normalizeName(...), $candidateNames);
        foreach ($localNames as $localName) {
            if (in_array($this->normalizeName($localName), $normalizedCandidateNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function alternativeNames(string $value): array
    {
        $values = preg_split('/[,;|]+/u', $value) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $name): string => trim($name), $values),
            static fn (string $name): bool => $name !== '' && $name !== '-',
        ));
    }

    private function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));

        return preg_replace('/[^\pL\pN]+/u', '', $normalized) ?? '';
    }

    private function distanceKm(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
    {
        $earthRadiusKm = 6371.0088;
        $latitudeDelta = deg2rad($otherLatitude - $latitude);
        $longitudeDelta = deg2rad($otherLongitude - $longitude);
        $value = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($value)));
    }

    private function entityQid(?string $value): ?string
    {
        return is_string($value)
            && preg_match('~^https?://www\.wikidata\.org/entity/(Q[0-9]+)$~', $value, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function coordinate(?string $value): ?array
    {
        if (! is_string($value)
            || preg_match('/^Point\(\s*(-?[0-9]+(?:\.[0-9]+)?)\s+(-?[0-9]+(?:\.[0-9]+)?)\s*\)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $longitude = (float) $matches[1];
        $latitude = (float) $matches[2];
        if (! is_finite($latitude) || ! is_finite($longitude)
            || $latitude < 0.5 || $latitude > 4.5 || $longitude < 97 || $longitude > 100.5) {
            return null;
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    private function decimal(?string $value): ?float
    {
        return is_string($value) && is_numeric($value) && is_finite((float) $value)
            ? (float) $value
            : null;
    }

    /**
     * @param  array<string, array{type: string, value: string}>  $row
     */
    private function binding(array $row, string $key): ?string
    {
        $value = $row[$key]['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, array{type: string, value: string}>  $row
     */
    private function textBinding(array $row, string $key): ?string
    {
        $binding = $row[$key] ?? null;
        if (! is_array($binding) || ! in_array($binding['type'] ?? null, ['literal', 'typed-literal'], true)) {
            return null;
        }

        return is_string($binding['value'] ?? null) ? $binding['value'] : null;
    }
}
