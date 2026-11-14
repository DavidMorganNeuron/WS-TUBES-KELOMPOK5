<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\Destination;
use Illuminate\Support\Collection;

final class SparqlRowMapper
{
    /**
     * @param  list<array<string, string|null>>  $rows
     * @return Collection<string, Destination>
     */
    public function destinations(array $rows): Collection
    {
        $destinations = collect();

        foreach ($rows as $row) {
            $id = $row['idDestinasi'] ?? null;
            $name = $row['namaDestinasi'] ?? null;
            if (! is_string($id) || preg_match('/^ws-\d{3}$/', $id) !== 1 || ! is_string($name) || trim($name) === '') {
                throw OntologyUnavailableException::badResponse();
            }

            if ($destinations->has($id)) {
                throw OntologyUnavailableException::badResponse();
            }

            $row['idDestinasi'] = $id;
            $destinations->put($id, Destination::fromArray($row));
        }

        return $destinations;
    }
}
