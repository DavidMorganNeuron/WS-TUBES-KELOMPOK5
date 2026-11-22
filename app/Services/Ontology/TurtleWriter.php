<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final class TurtleWriter
{
    /**
     * @var array<string, string>
     */
    private const PREFIXES = [
        'rdf' => OntologyMigrator::RDF_NS,
        'rdfs' => OntologyMigrator::RDFS_NS,
        'owl' => OntologyMigrator::OWL_NS,
        'xsd' => OntologyMigrator::XSD_NS,
        'ws' => OntologyMigrator::WS_NS,
        'data' => OntologyMigrator::DATA_NS,
    ];

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    public function write(array $triples): string
    {
        $statements = [];
        foreach ($triples as $triple) {
            $statements[] = $this->formatIri($triple['subject'])
                .' '.$this->formatIri($triple['predicate'])
                .' '.$this->formatTerm($triple['object'])
                .' .';
        }

        $statements = array_values(array_unique($statements));
        sort($statements, SORT_STRING);

        $prefixLines = [];
        foreach (self::PREFIXES as $prefix => $namespace) {
            $prefixLines[] = "@prefix {$prefix}: <{$namespace}> .";
        }

        return implode("\n", $prefixLines)."\n\n".implode("\n", $statements)."\n";
    }

    /**
     * @param  array{kind: string, value: string, datatype: ?string, language: ?string}  $term
     */
    private function formatTerm(array $term): string
    {
        if ($term['kind'] === 'iri') {
            return $this->formatIri($term['value']);
        }

        if ($term['kind'] !== 'literal') {
            throw new InvalidArgumentException('Jenis term RDF tidak dikenal.');
        }

        $literal = '"'.$this->escapeLiteral($term['value']).'"';
        if ($term['language'] !== null) {
            return $literal.'@'.$term['language'];
        }

        return $literal.'^^'.$this->formatIri($term['datatype'] ?? OntologyMigrator::XSD_NS.'string');
    }

    private function formatIri(string $iri): string
    {
        foreach (self::PREFIXES as $prefix => $namespace) {
            if (! str_starts_with($iri, $namespace)) {
                continue;
            }

            $localName = substr($iri, strlen($namespace));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/', $localName) === 1) {
                return $prefix.':'.$localName;
            }
        }

        return '<'.$iri.'>';
    }

    private function escapeLiteral(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);
    }
}
