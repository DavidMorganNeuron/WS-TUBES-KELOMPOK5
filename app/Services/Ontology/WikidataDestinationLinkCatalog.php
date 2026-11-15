<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;
use JsonException;

final class WikidataDestinationLinkCatalog
{
    public function __construct(private readonly string $path) {}

    /**
     * @return array{
     *     schema_version: int,
     *     setuju: list<array{id: string, qid: string, reason?: string}>,
     *     tolak: list<array{id: string, qid: string, reason?: string}>,
     *     destinations_by_id: array<string, string>,
     *     destinations_by_qid: array<string, string>
     * }
     */
    public function load(): array
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException("Berkas persetujuan Wikidata destinasi tidak ditemukan: {$this->path}");
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new InvalidArgumentException("Berkas persetujuan Wikidata destinasi tidak dapat dibaca: {$this->path}");
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Berkas persetujuan Wikidata destinasi bukan JSON yang valid.', previous: $exception);
        }

        if (! is_array($document)
            || ($document['schema_version'] ?? null) !== 1
            || ! is_array($document['setuju'] ?? null)
            || ! array_is_list($document['setuju'])
            || ! is_array($document['tolak'] ?? null)
            || ! array_is_list($document['tolak'])) {
            throw new InvalidArgumentException('Struktur berkas persetujuan Wikidata destinasi tidak valid.');
        }

        $destinationsById = [];
        $destinationsByQid = [];
        $approved = $this->validatedRows($document['setuju'], 'setuju', $destinationsById, $destinationsByQid);
        $rejectedIds = [];
        $rejectedQids = [];
        $rejected = $this->validatedRows($document['tolak'], 'tolak', $rejectedIds, $rejectedQids);
        if (array_intersect_key($destinationsByQid, $rejectedQids) !== []
            || array_intersect_key($destinationsById, $rejectedIds) !== []) {
            throw new InvalidArgumentException('ID destinasi atau QID Wikidata tidak boleh sekaligus disetujui dan ditolak.');
        }

        usort($approved, static fn (array $left, array $right): int => [$left['id'], $left['qid']] <=> [$right['id'], $right['qid']]);
        usort($rejected, static fn (array $left, array $right): int => [$left['id'], $left['qid']] <=> [$right['id'], $right['qid']]);
        ksort($destinationsById, SORT_STRING);
        ksort($destinationsByQid, SORT_STRING);

        return [
            'schema_version' => 1,
            'setuju' => $approved,
            'tolak' => $rejected,
            'destinations_by_id' => $destinationsById,
            'destinations_by_qid' => $destinationsByQid,
        ];
    }

    /**
     * @param  list<mixed>  $rows
     * @param  array<string, string>  $ids
     * @param  array<string, string>  $qids
     * @return list<array{id: string, qid: string, reason?: string}>
     */
    private function validatedRows(array $rows, string $kind, array &$ids, array &$qids): array
    {
        $validated = [];
        foreach ($rows as $row) {
            if (! is_array($row)
                || ! is_string($row['id'] ?? null)
                || preg_match('/^[A-Za-z0-9_-]+$/', $row['id']) !== 1
                || ! is_string($row['qid'] ?? null)
                || preg_match('/^Q[0-9]+$/', $row['qid']) !== 1
                || (isset($row['reason']) && (! is_string($row['reason']) || trim($row['reason']) === ''))) {
                throw new InvalidArgumentException("Entri {$kind} pada berkas persetujuan Wikidata destinasi tidak valid.");
            }

            if (isset($ids[$row['id']]) || isset($qids[$row['qid']])) {
                throw new InvalidArgumentException("ID destinasi atau QID duplikat pada daftar {$kind} Wikidata.");
            }

            $ids[$row['id']] = $row['qid'];
            $qids[$row['qid']] = $row['id'];
            $entry = ['id' => $row['id'], 'qid' => $row['qid']];
            if (isset($row['reason'])) {
                $entry['reason'] = trim($row['reason']);
            }
            $validated[] = $entry;
        }

        return $validated;
    }
}
