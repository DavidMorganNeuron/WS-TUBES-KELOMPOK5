<?php

declare(strict_types=1);

namespace App\Services\Ontology;

final class OntologyGraphMapper
{
    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @return list<array<string, string|null>>
     */
    public function destinations(array $triples): array
    {
        $rdfType = OntologyMigrator::RDF_NS.'type';
        $rdfsLabel = OntologyMigrator::RDFS_NS.'label';
        $namespace = OntologyMigrator::WS_NS;
        $types = [];
        $properties = [];

        foreach ($triples as $triple) {
            $value = $triple['object']['value'];
            $properties[$triple['subject']][$triple['predicate']][] = $value;
            if ($triple['predicate'] === $rdfType) {
                $types[$triple['subject']][$value] = true;
            }
        }

        $rows = [];
        foreach ($types as $subject => $subjectTypes) {
            if (! isset($subjectTypes[$namespace.'DestinasiWisata'])) {
                continue;
            }

            $row = [
                's' => $subject,
                'idDestinasi' => $this->first($properties, $subject, 'idDestinasi', $namespace),
                'namaDestinasi' => $this->first($properties, $subject, 'namaDestinasi', $namespace),
                'namaAlternatif' => $this->joined($properties, $subject, 'namaAlternatif', $namespace),
                'deskripsiSingkat' => $this->first($properties, $subject, 'deskripsiSingkat', $namespace),
                'deskripsiLengkap' => $this->first($properties, $subject, 'deskripsiLengkap', $namespace),
                'kataKunci' => $this->joined($properties, $subject, 'kataKunci', $namespace),
                'beradaDi' => $this->relatedLabel($properties, $subject, 'berlokasiDi', $namespace, $rdfsLabel),
                'alamat' => $this->first($properties, $subject, 'alamat', $namespace),
                'lintang' => $this->first($properties, $subject, 'lintang', $namespace),
                'bujur' => $this->first($properties, $subject, 'bujur', $namespace),
                'petunjukLokasi' => $this->first($properties, $subject, 'petunjukLokasi', $namespace),
                'jamOperasional' => $this->openingHours($properties, $subject, $namespace),
                'hargaMasuk' => $this->prices($properties, $subject, $namespace),
                'noHpResmi' => $this->contactValue($properties, $subject, 'nomorHp', $namespace),
                'emailResmi' => $this->contactValue($properties, $subject, 'email', $namespace),
                'instagramResmi' => $this->contactValue($properties, $subject, 'urlInstagram', $namespace),
                'jenisDestinasi' => $this->relatedLabels($properties, $subject, 'berkategori', $namespace, $rdfsLabel),
                'memilikiFasilitas' => $this->relatedLabels($properties, $subject, 'menyediakanFasilitas', $namespace, $rdfsLabel),
                'dapatMelakukan' => $this->relatedLabels($properties, $subject, 'menawarkanAktivitas', $namespace, $rdfsLabel),
                'memilikiMedia' => $this->media($properties, $subject, $namespace),
                'ratingPengunjung' => $this->first($properties, $subject, 'ratingPengunjung', $namespace),
            ];
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function first(array $properties, string $subject, string $property, string $namespace): ?string
    {
        return $properties[$subject][$namespace.$property][0] ?? null;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function joined(array $properties, string $subject, string $property, string $namespace): ?string
    {
        $values = array_values(array_filter(
            $properties[$subject][$namespace.$property] ?? [],
            static fn (string $value): bool => $value !== '-',
        ));

        return $values === [] ? null : implode(' | ', array_values(array_unique($values)));
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function relatedLabel(
        array $properties,
        string $subject,
        string $relation,
        string $namespace,
        string $labelProperty,
    ): ?string {
        $resource = $properties[$subject][$namespace.$relation][0] ?? null;
        if ($resource === null) {
            return null;
        }

        return $properties[$resource][$labelProperty][0] ?? null;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function relatedLabels(
        array $properties,
        string $subject,
        string $relation,
        string $namespace,
        string $labelProperty,
    ): ?string {
        $values = [];
        foreach ($properties[$subject][$namespace.$relation] ?? [] as $resource) {
            foreach ($properties[$resource][$labelProperty] ?? [] as $value) {
                if ($value !== '-') {
                    $values[] = $value;
                }
            }
        }

        $values = array_values(array_unique($values));

        return $values === [] ? null : implode(' | ', $values);
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function media(array $properties, string $subject, string $namespace): ?string
    {
        foreach ($properties[$subject][$namespace.'memilikiMedia'] ?? [] as $resource) {
            foreach ($properties[$resource][$namespace.'urlMedia'] ?? [] as $url) {
                if ($url !== '-') {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function contactValue(
        array $properties,
        string $subject,
        string $property,
        string $namespace,
    ): ?string {
        foreach ($properties[$subject][$namespace.'memilikiKontak'] ?? [] as $contact) {
            foreach ($properties[$contact][$namespace.$property] ?? [] as $value) {
                if ($value !== '-') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function openingHours(array $properties, string $subject, string $namespace): ?string
    {
        foreach ($properties[$subject][$namespace.'memilikiJadwal'] ?? [] as $schedule) {
            $description = $properties[$schedule][$namespace.'keteranganJadwal'][0] ?? null;
            if ($description !== null && $description !== '-') {
                return $description;
            }

            $opening = $properties[$schedule][$namespace.'jamBuka'][0] ?? null;
            $closing = $properties[$schedule][$namespace.'jamTutup'][0] ?? null;
            if ($opening !== null && $closing !== null) {
                return $opening.' - '.$closing;
            }

            if (($properties[$schedule][$namespace.'bukaSepanjangWaktu'][0] ?? null) === 'true') {
                return 'Selalu Buka';
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $properties
     */
    private function prices(array $properties, string $subject, string $namespace): ?string
    {
        $values = [];
        foreach ($properties[$subject][$namespace.'memilikiTarif'] ?? [] as $tariff) {
            $price = $properties[$tariff][$namespace.'hargaMasuk'][0] ?? null;
            $description = $properties[$tariff][$namespace.'keteranganTarif'][0] ?? null;
            $value = $price !== null && $price !== '-' ? $price : $description;
            if ($value !== null && $value !== '-') {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values));

        return $values === [] ? null : implode(' | ', $values);
    }
}
