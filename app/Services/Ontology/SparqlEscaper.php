<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;
use JsonException;

final class SparqlEscaper
{
    public function literal(string $value): string
    {
        try {
            $literal = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Literal SPARQL tidak dapat dienkode.', previous: $exception);
        }

        return $literal;
    }

    public function destinationIri(string $id): string
    {
        if (preg_match('/^ws-\d{3}$/', $id) !== 1) {
            throw new InvalidArgumentException('ID destinasi tidak aman untuk IRI SPARQL.');
        }

        return '<'.OntologyMigrator::DATA_NS.$id.'>';
    }

    public function number(int|float $value): string
    {
        if (! is_finite((float) $value)) {
            throw new InvalidArgumentException('Nilai numerik SPARQL tidak valid.');
        }

        return (string) $value;
    }
}
