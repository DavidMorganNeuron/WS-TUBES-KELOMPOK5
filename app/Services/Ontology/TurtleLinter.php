<?php

namespace App\Services\Ontology;

use RuntimeException;

/**
 * Linter ringan untuk dataset Turtle WayFinder (satu pasangan predikat-objek per baris,
 * blok destinasi dipisahkan baris kosong). Menunjuk nomor baris sumber pelanggaran
 * sebelum data masuk ke Fuseki.
 */
final class TurtleLinter
{
    /**
     * @return array{blocks: int, violations: list<array{subject: string, property: string, lines: list<int>, values: list<string>}>}
     */
    public function lint(string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException("File Turtle tidak dapat dibaca: {$path}");
        }

        $blocks = [];
        $occurrences = [];
        $subject = null;

        foreach ($lines as $index => $raw) {
            $number = $index + 1;
            $line = rtrim($raw, "\r");

            if (preg_match('/^(\S+)\s+a\s+ws:DestinasiWisata\b/', $line, $match) === 1) {
                $subject = $match[1];
                $blocks[$subject][] = $number;

                continue;
            }

            if (trim($line) === '') {
                $subject = null;

                continue;
            }

            if ($subject !== null && preg_match('/^\s+ws:(\w+)\s+(.+?)\s*[;.]\s*$/', $line, $match) === 1) {
                $occurrences[$subject][$match[1]][] = ['line' => $number, 'value' => $match[2]];
            }
        }

        return [
            'blocks' => count($blocks),
            'violations' => [...$this->duplicateBlocks($blocks), ...$this->cardinality($occurrences)],
        ];
    }

    /**
     * @param  array<string, list<int>>  $blocks
     * @return list<array{subject: string, property: string, lines: list<int>, values: list<string>}>
     */
    private function duplicateBlocks(array $blocks): array
    {
        $violations = [];

        foreach ($blocks as $subject => $lines) {
            if (count($lines) > 1) {
                $violations[] = ['subject' => $subject, 'property' => '(blok ganda)', 'lines' => $lines, 'values' => []];
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, array<string, list<array{line: int, value: string}>>>  $occurrences
     * @return list<array{subject: string, property: string, lines: list<int>, values: list<string>}>
     */
    private function cardinality(array $occurrences): array
    {
        $violations = [];

        foreach ($occurrences as $subject => $properties) {
            foreach (OntologyInspector::SINGLE_VALUED as $property) {
                $entries = $properties[$property] ?? [];
                $values = array_values(array_unique(array_column($entries, 'value')));

                if (count($values) > 1) {
                    $violations[] = [
                        'subject' => $subject,
                        'property' => $property,
                        'lines' => array_column($entries, 'line'),
                        'values' => $values,
                    ];
                }
            }
        }

        return $violations;
    }
}