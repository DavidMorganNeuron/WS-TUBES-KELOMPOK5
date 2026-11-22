<?php

namespace App\Services\Ontology;

use RuntimeException;

final class OntologyDestinationIdConsistency
{
    public function __construct(private readonly OntologyGraphMapper $graphMapper) {}

    public function validate(array $sourceRows, array $v3Triples): void
    {
        $sourceIds = array_values(array_unique(array_filter(
            array_map(
                static fn (array $row): mixed => $row['idDestinasi'] ?? null,
                $sourceRows,
            ),
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($sourceIds === []) {
            throw new RuntimeException('Sumber lokal v2 kosong atau tidak memiliki idDestinasi.');
        }

        $v3Ids = array_values(array_unique(array_filter(
            array_map(
                static fn (array $row): mixed => $row['idDestinasi'] ?? null,
                $this->graphMapper->destinations($v3Triples),
            ),
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($v3Ids === []) {
            throw new RuntimeException('Graph v3 kosong atau tidak memiliki idDestinasi.');
        }

        sort($sourceIds, SORT_STRING);
        sort($v3Ids, SORT_STRING);

        if ($sourceIds !== $v3Ids) {
            $missing = array_values(array_diff($sourceIds, $v3Ids));
            $extra = array_values(array_diff($v3Ids, $sourceIds));
            throw new RuntimeException(
                'Himpunan ID sumber v2 dan graph v3 berbeda. '
                .'Jalankan php artisan ontology:migrate-v3 terlebih dahulu.'
                .($missing !== [] ? ' Hilang: '.implode(', ', $missing).'.' : '')
                .($extra !== [] ? ' Tambahan: '.implode(', ', $extra).'.' : ''),
            );
        }
    }
}
