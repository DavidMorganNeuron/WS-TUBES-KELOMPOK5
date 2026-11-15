<?php

declare(strict_types=1);

namespace App\Services\Ontology;

final class OntologyValidator
{
    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @return array{
     *     errors: list<string>,
     *     class_counts: array<string, int>,
     *     object_property_counts: array<string, int>,
     *     data_property_counts: array<string, int>,
     *     destination_count: int,
     *     same_as_counts: array{regions: int, destinations: int},
     *     sentinel_counts: array<string, int>,
     *     rating_stats: array{rated: int, unrated: int, average: float|null},
     *     source_comparison: array{destination_count: int, missing_ids: list<string>, extra_ids: list<string>}|null
     * }
     */
    public function validate(
        array $triples,
        string $unknownLabel,
        ?array $sourceIds = null,
        ?array $regionQidsByName = null,
        ?array $destinationQidsById = null,
    ): array {
        if ($unknownLabel === '') {
            throw new \InvalidArgumentException('Sentinel ontologi tidak boleh kosong.');
        }

        $errors = [];
        $types = [];
        $properties = [];
        $classCounts = array_fill_keys(OntologyMigrator::CLASSES, 0);
        $objectPropertyCounts = array_fill_keys(array_keys(OntologyMigrator::OBJECT_PROPERTIES), 0);
        $dataPropertyCounts = array_fill_keys(array_keys(OntologyMigrator::DATA_PROPERTIES), 0);
        $sentinelCounts = array_fill_keys(array_keys(OntologyMigrator::DATA_PROPERTIES), 0);

        foreach ($triples as $triple) {
            $subject = $triple['subject'];
            $predicate = $triple['predicate'];
            $object = $triple['object'];
            $properties[$subject][$predicate][] = $object;

            if ($predicate === OntologyMigrator::RDF_NS.'type' && $object['kind'] === 'iri') {
                $types[$subject][$object['value']] = true;
                $className = $this->localName($object['value'], OntologyMigrator::WS_NS);
                if (isset($classCounts[$className])) {
                    $classCounts[$className]++;
                }
            }

            if ($object['kind'] === 'literal' && trim($object['value']) === '') {
                $errors[] = "{$subject}: ditemukan literal string kosong pada {$predicate}.";
            }

            $propertyName = $this->localName($predicate, OntologyMigrator::WS_NS);
            if (isset($objectPropertyCounts[$propertyName])) {
                $objectPropertyCounts[$propertyName]++;
            } elseif (isset($dataPropertyCounts[$propertyName])) {
                $dataPropertyCounts[$propertyName]++;
                if ($object['kind'] === 'literal' && $object['value'] === $unknownLabel) {
                    $sentinelCounts[$propertyName]++;
                }
            } elseif (str_starts_with($predicate, OntologyMigrator::WS_NS)
                && $predicate !== OntologyMigrator::RDF_NS.'type') {
                $errors[] = "Properti ontologi tidak dikenal: {$propertyName}.";
            }
        }

        foreach (OntologyMigrator::CLASSES as $class) {
            $classIri = OntologyMigrator::WS_NS.$class;
            if (! isset($types[$classIri][OntologyMigrator::OWL_NS.'Class'])) {
                $errors[] = "Class {$class} tidak dideklarasikan sebagai owl:Class.";
            }
        }

        foreach (OntologyMigrator::OBJECT_PROPERTIES as $property => $definition) {
            $propertyIri = OntologyMigrator::WS_NS.$property;
            if (! isset($types[$propertyIri][OntologyMigrator::OWL_NS.'ObjectProperty'])) {
                $errors[] = "Object property {$property} tidak dideklarasikan sebagai owl:ObjectProperty.";
            }
            $this->validateSchemaDeclaration(
                $errors,
                $properties,
                $propertyIri,
                OntologyMigrator::RDFS_NS.'domain',
                OntologyMigrator::WS_NS.$definition['domain'],
                $property,
            );
            $this->validateSchemaDeclaration(
                $errors,
                $properties,
                $propertyIri,
                OntologyMigrator::RDFS_NS.'range',
                OntologyMigrator::WS_NS.$definition['range'],
                $property,
            );
            if (($definition['functional'] ?? false)
                && ! isset($types[$propertyIri][OntologyMigrator::OWL_NS.'FunctionalProperty'])) {
                $errors[] = "Object property {$property} tidak dideklarasikan sebagai owl:FunctionalProperty.";
            }
            if (($definition['symmetric'] ?? false)
                && ! isset($types[$propertyIri][OntologyMigrator::OWL_NS.'SymmetricProperty'])) {
                $errors[] = "Object property {$property} tidak dideklarasikan sebagai owl:SymmetricProperty.";
            }
        }

        foreach (OntologyMigrator::DATA_PROPERTIES as $property => $definition) {
            $propertyIri = OntologyMigrator::WS_NS.$property;
            if (! isset($types[$propertyIri][OntologyMigrator::OWL_NS.'DatatypeProperty'])) {
                $errors[] = "Data property {$property} tidak dideklarasikan sebagai owl:DatatypeProperty.";
            }
            $this->validateSchemaDeclaration(
                $errors,
                $properties,
                $propertyIri,
                OntologyMigrator::RDFS_NS.'domain',
                OntologyMigrator::WS_NS.$definition['domain'],
                $property,
            );
            $this->validateSchemaDeclaration(
                $errors,
                $properties,
                $propertyIri,
                OntologyMigrator::RDFS_NS.'range',
                $this->rangeIri($definition['range']),
                $property,
            );
            if (($definition['functional'] ?? false)
                && ! isset($types[$propertyIri][OntologyMigrator::OWL_NS.'FunctionalProperty'])) {
                $errors[] = "Data property {$property} tidak dideklarasikan sebagai owl:FunctionalProperty.";
            }
        }

        foreach ($classCounts as $class => $count) {
            if ($count === 0) {
                $errors[] = "Class {$class} tidak memiliki instance.";
            }
        }

        foreach ($objectPropertyCounts as $property => $count) {
            if ($count === 0) {
                $errors[] = "Object property {$property} tidak digunakan.";
            }
        }

        foreach ($dataPropertyCounts as $property => $count) {
            if ($count === 0) {
                $errors[] = "Data property {$property} tidak digunakan.";
            }
        }

        foreach (OntologyMigrator::OBJECT_PROPERTIES as $property => $definition) {
            $propertyIri = OntologyMigrator::WS_NS.$property;
            foreach ($triples as $triple) {
                if ($triple['predicate'] !== $propertyIri) {
                    continue;
                }

                $this->validateDomain($errors, $types, $triple['subject'], $definition['domain'], $property);
                $this->validateObjectRange($errors, $types, $triple['object'], $definition['range'], $property);
            }

            if ($definition['functional'] ?? false) {
                $this->validateCardinality($errors, $properties, $propertyIri, $property, 1);
            }

            if ($definition['symmetric'] ?? false) {
                $this->validateSymmetry($errors, $triples, $propertyIri, $property);
            }
        }

        foreach (OntologyMigrator::DATA_PROPERTIES as $property => $definition) {
            $propertyIri = OntologyMigrator::WS_NS.$property;
            foreach ($triples as $triple) {
                if ($triple['predicate'] !== $propertyIri) {
                    continue;
                }

                $this->validateDomain($errors, $types, $triple['subject'], $definition['domain'], $property);
                $this->validateDataRange($errors, $triple['object'], $definition['range'], $property, $triple['subject']);
            }

            if ($definition['functional'] ?? false) {
                $this->validateCardinality($errors, $properties, $propertyIri, $property, 1);
            }
        }

        $destinationIri = OntologyMigrator::WS_NS.'DestinasiWisata';
        $destinationSubjects = [];
        foreach ($types as $subject => $subjectTypes) {
            if (isset($subjectTypes[$destinationIri])) {
                $destinationSubjects[] = $subject;
            }
        }

        $ids = [];
        $ratedCount = 0;
        $ratingTotal = 0.0;
        foreach ($destinationSubjects as $subject) {
            $idValues = $properties[$subject][OntologyMigrator::WS_NS.'idDestinasi'] ?? [];
            if (count($idValues) !== 1 || $idValues[0]['kind'] !== 'literal') {
                $errors[] = "{$subject}: idDestinasi harus memiliki tepat satu literal.";
            } else {
                $id = $idValues[0]['value'];
                if (isset($ids[$id])) {
                    $errors[] = "idDestinasi duplikat: {$id}.";
                }
                $ids[$id] = true;
            }

            $ratingValues = $properties[$subject][OntologyMigrator::WS_NS.'ratingPengunjung'] ?? [];
            if (count($ratingValues) !== 1 || $ratingValues[0]['kind'] !== 'literal') {
                $errors[] = "{$subject}: ratingPengunjung harus memiliki tepat satu literal.";

                continue;
            }

            $rating = $ratingValues[0];
            $isUnknownRating = $rating['value'] === $unknownLabel
                && $rating['datatype'] === OntologyMigrator::XSD_NS.'string'
                && $rating['language'] === null;
            $isNumericRating = $rating['datatype'] === OntologyMigrator::XSD_NS.'decimal'
                && $this->isDecimal($rating['value'])
                && (float) $rating['value'] >= 0
                && (float) $rating['value'] <= 5;

            if (! $isUnknownRating && ! $isNumericRating) {
                $errors[] = "{$subject}: ratingPengunjung harus decimal 0..5 atau literal persis \"{$unknownLabel}\".";
            } elseif ($isNumericRating) {
                $ratedCount++;
                $ratingTotal += (float) $rating['value'];
            }
        }

        $sourceComparison = null;
        if ($sourceIds !== null) {
            $sourceIdSet = [];
            foreach ($sourceIds as $sourceId) {
                $sourceId = trim($sourceId);
                if ($sourceId !== '') {
                    $sourceIdSet[$sourceId] = true;
                }
            }

            $v3IdSet = array_fill_keys(array_keys($ids), true);
            $missingIds = array_values(array_diff(array_keys($sourceIdSet), array_keys($v3IdSet)));
            $extraIds = array_values(array_diff(array_keys($v3IdSet), array_keys($sourceIdSet)));
            sort($missingIds);
            sort($extraIds);
            $sourceComparison = [
                'destination_count' => count($sourceIdSet),
                'missing_ids' => $missingIds,
                'extra_ids' => $extraIds,
            ];

            if (count($sourceIdSet) !== count($v3IdSet)) {
                $errors[] = 'Jumlah destinasi v2 dan v3 berbeda: '.count($sourceIdSet).' != '.count($v3IdSet).'.';
            }
            foreach ($missingIds as $id) {
                $errors[] = "Destinasi sumber v2 tidak ditemukan di v3: {$id}.";
            }
            foreach ($extraIds as $id) {
                $errors[] = "Destinasi v3 tidak ada pada sumber v2: {$id}.";
            }
        }

        foreach ($triples as $triple) {
            if ($triple['predicate'] === OntologyMigrator::WS_NS.'hargaMasuk') {
                $object = $triple['object'];
                if ($object['kind'] !== 'literal'
                    || $object['value'] === $unknownLabel
                    || $object['datatype'] !== OntologyMigrator::XSD_NS.'decimal'
                    || ! $this->isDecimal($object['value'])) {
                    $errors[] = "{$triple['subject']}: hargaMasuk harus xsd:decimal dan tidak boleh \"{$unknownLabel}\".";
                }
            }

            if (in_array($triple['predicate'], [
                OntologyMigrator::WS_NS.'namaAlternatif',
                OntologyMigrator::WS_NS.'kataKunci',
            ], true) && $triple['object']['kind'] === 'literal' && $triple['object']['value'] === $unknownLabel) {
                $errors[] = "{$triple['subject']}: nilai \"{$unknownLabel}\" dilarang pada properti multi-nilai.";
            }

            if (in_array($triple['predicate'], [
                OntologyMigrator::WS_NS.'lintang',
                OntologyMigrator::WS_NS.'bujur',
            ], true)) {
                $object = $triple['object'];
                $value = $object['value'];
                $valid = $object['kind'] === 'literal'
                    && $object['datatype'] === OntologyMigrator::XSD_NS.'decimal'
                    && $this->isDecimal($value);
                if (! $valid) {
                    $errors[] = "{$triple['subject']}: {$this->localName($triple['predicate'], OntologyMigrator::WS_NS)} harus xsd:decimal.";

                    continue;
                }

                $latitude = $triple['predicate'] === OntologyMigrator::WS_NS.'lintang';
                $minimum = $latitude ? 0.5 : 97.0;
                $maximum = $latitude ? 4.5 : 100.5;
                if ((float) $value < $minimum || (float) $value > $maximum) {
                    $errors[] = "{$triple['subject']}: koordinat di luar batas Sumatera Utara ({$value}).";
                }
            }
        }

        $sameAsCounts = $this->validateSameAs(
            $errors,
            $triples,
            $types,
            $properties,
            $regionQidsByName,
            $destinationQidsById,
        );

        return [
            'errors' => array_values(array_unique($errors)),
            'class_counts' => $classCounts,
            'object_property_counts' => $objectPropertyCounts,
            'data_property_counts' => $dataPropertyCounts,
            'destination_count' => count($destinationSubjects),
            'same_as_counts' => $sameAsCounts,
            'sentinel_counts' => $sentinelCounts,
            'rating_stats' => [
                'rated' => $ratedCount,
                'unrated' => count($destinationSubjects) - $ratedCount,
                'average' => $ratedCount === 0 ? null : round($ratingTotal / $ratedCount, 2),
            ],
            'source_comparison' => $sourceComparison,
        ];
    }

    /**
     * @param  list<string>  $errors
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  array<string, array<string, bool>>  $types
     * @param  array<string, array<string, list<array{kind: string, value: string, datatype: ?string, language: ?string}>>>  $properties
     * @param  array<string, string>|null  $regionQidsByName
     * @param  array<string, string>|null  $destinationQidsById
     * @return array{regions: int, destinations: int}
     */
    private function validateSameAs(
        array &$errors,
        array $triples,
        array $types,
        array $properties,
        ?array $regionQidsByName,
        ?array $destinationQidsById,
    ): array {
        $sameAsPredicate = OntologyMigrator::OWL_NS.'sameAs';
        $destinationType = OntologyMigrator::WS_NS.'DestinasiWisata';
        $regionType = OntologyMigrator::WS_NS.'KabupatenKota';
        $regionSubjects = [];
        $destinationSubjects = [];
        $sameAsBySubject = [];

        foreach ($types as $subject => $subjectTypes) {
            if (isset($subjectTypes[$regionType])) {
                $regionSubjects[$subject] = true;
            }
            if (isset($subjectTypes[$destinationType])) {
                $destinationSubjects[$subject] = true;
            }
        }

        foreach ($triples as $triple) {
            if ($triple['predicate'] !== $sameAsPredicate) {
                continue;
            }

            $sameAsBySubject[$triple['subject']][] = $triple['object'];
            if (! isset($regionSubjects[$triple['subject']])
                && ! isset($destinationSubjects[$triple['subject']])) {
                $errors[] = "{$triple['subject']}: owl:sameAs hanya diizinkan pada wilayah atau destinasi.";
            }
        }

        $approvedById = [];
        $approvedByQid = [];
        foreach ($destinationQidsById ?? [] as $id => $qid) {
            if (! is_string($id) || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1
                || ! is_string($qid) || preg_match('/^Q[0-9]+$/', $qid) !== 1
                || isset($approvedById[$id]) || isset($approvedByQid[$qid])) {
                $errors[] = 'Pemetaan persetujuan sameAs destinasi tidak valid atau mengandung ID/QID duplikat.';

                continue;
            }
            $approvedById[$id] = $qid;
            $approvedByQid[$qid] = $id;
        }

        $qidOwners = [];
        if ($regionQidsByName !== null) {
            if (count($regionSubjects) !== count($regionQidsByName)) {
                $errors[] = 'Jumlah resource Kabupaten/Kota tidak sama dengan snapshot Wikidata.';
            }

            $expectedSubjects = [];
            foreach ($regionQidsByName as $name => $qid) {
                if (! is_string($name) || trim($name) === ''
                    || ! is_string($qid) || preg_match('/^Q[0-9]+$/', $qid) !== 1
                    || isset($qidOwners[$qid])) {
                    $errors[] = 'Pemetaan nama wilayah dan QID pada snapshot Wikidata tidak valid atau tidak satu-ke-satu.';

                    continue;
                }
                $qidOwners[$qid] = OntologyMigrator::DATA_NS.'wilayah-'.$name;

                $subjects = [];
                foreach (array_keys($regionSubjects) as $subject) {
                    $labels = $properties[$subject][OntologyMigrator::RDFS_NS.'label'] ?? [];
                    foreach ($labels as $label) {
                        if ($label['kind'] === 'literal' && $label['value'] === $name) {
                            $subjects[$subject] = true;
                            break;
                        }
                    }
                }

                if (count($subjects) !== 1) {
                    $errors[] = "Wilayah {$name}: harus memiliki tepat satu resource Kabupaten/Kota.";

                    continue;
                }

                $subject = array_key_first($subjects);
                $expectedSubjects[$subject] = true;
                $sameAs = $sameAsBySubject[$subject] ?? [];
                $expectedIri = OntologyMigrator::WIKIDATA_ENTITY_NS.$qid;
                if (count($sameAs) !== 1
                    || ($sameAs[0]['kind'] ?? null) !== 'iri'
                    || ($sameAs[0]['value'] ?? null) !== $expectedIri) {
                    $errors[] = "Wilayah {$name}: owl:sameAs harus menunjuk tepat ke {$expectedIri}.";
                }
            }

            foreach (array_keys($regionSubjects) as $subject) {
                if (! isset($expectedSubjects[$subject])) {
                    $errors[] = "{$subject}: owl:sameAs wilayah tidak sesuai dengan nama pada snapshot.";
                }
                if (count($sameAsBySubject[$subject] ?? []) !== 1) {
                    $errors[] = "{$subject}: wilayah harus memiliki tepat satu owl:sameAs.";
                }
            }
        }

        foreach (array_keys($destinationSubjects) as $subject) {
            $sameAs = $sameAsBySubject[$subject] ?? [];
            if (count($sameAs) > 1) {
                $errors[] = "{$subject}: destinasi hanya boleh memiliki paling banyak satu owl:sameAs.";
            }

            $idObjects = $properties[$subject][OntologyMigrator::WS_NS.'idDestinasi'] ?? [];
            $id = count($idObjects) === 1 && ($idObjects[0]['kind'] ?? null) === 'literal'
                ? $idObjects[0]['value']
                : null;
            $expectedQid = is_string($id) ? ($approvedById[$id] ?? null) : null;
            if ($expectedQid === null && $sameAs !== []) {
                $errors[] = "{$subject}: owl:sameAs destinasi tidak diizinkan tanpa QID pada berkas persetujuan.";
            }
            if ($expectedQid !== null && count($sameAs) !== 1) {
                $errors[] = "{$subject}: QID yang disetujui harus memiliki tepat satu owl:sameAs.";
            }

            foreach ($sameAs as $object) {
                if (($object['kind'] ?? null) !== 'iri'
                    || preg_match('~^'.preg_quote(OntologyMigrator::WIKIDATA_ENTITY_NS, '~').'(Q[0-9]+)$~D', (string) ($object['value'] ?? ''), $matches) !== 1) {
                    $errors[] = "{$subject}: owl:sameAs destinasi harus berupa IRI Wikidata kanonik.";

                    continue;
                }

                $qid = $matches[1];
                if (isset($qidOwners[$qid]) || (isset($approvedByQid[$qid]) && $approvedByQid[$qid] !== $id)) {
                    $errors[] = "{$subject}: QID Wikidata {$qid} dipakai lebih dari satu resource.";
                }
                $qidOwners[$qid] = $subject;
                if ($expectedQid !== $qid) {
                    $errors[] = "{$subject}: QID owl:sameAs tidak cocok dengan persetujuan untuk ID {$id}.";
                }
            }
        }

        foreach ($approvedById as $id => $qid) {
            $subject = OntologyMigrator::DATA_NS.$id;
            if (! isset($destinationSubjects[$subject])) {
                $errors[] = "Persetujuan sameAs untuk {$id} tidak memiliki resource destinasi.";

                continue;
            }

            $sameAs = $sameAsBySubject[$subject] ?? [];
            $expectedIri = OntologyMigrator::WIKIDATA_ENTITY_NS.$qid;
            if (count($sameAs) !== 1
                || ($sameAs[0]['kind'] ?? null) !== 'iri'
                || ($sameAs[0]['value'] ?? null) !== $expectedIri) {
                $errors[] = "Destinasi {$id}: owl:sameAs harus menunjuk tepat ke {$expectedIri}.";
            }
        }

        return [
            'regions' => array_sum(array_map(
                static fn (string $subject): int => count($sameAsBySubject[$subject] ?? []),
                array_keys($regionSubjects),
            )),
            'destinations' => array_sum(array_map(
                static fn (string $subject): int => count($sameAsBySubject[$subject] ?? []),
                array_keys($destinationSubjects),
            )),
        ];
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, array<string, bool>>  $types
     */
    private function validateDomain(array &$errors, array $types, string $subject, string $domain, string $property): void
    {
        if (! isset($types[$subject][OntologyMigrator::WS_NS.$domain])) {
            $errors[] = "{$subject}: domain {$domain} tidak terpenuhi untuk {$property}.";
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, array<string, list<array{kind: string, value: string, datatype: ?string, language: ?string}>>>  $properties
     */
    private function validateSchemaDeclaration(
        array &$errors,
        array $properties,
        string $subject,
        string $predicate,
        string $expectedIri,
        string $property,
    ): void {
        $values = $properties[$subject][$predicate] ?? [];
        if (count($values) !== 1 || $values[0]['kind'] !== 'iri' || $values[0]['value'] !== $expectedIri) {
            $localName = $this->localName($predicate, OntologyMigrator::RDFS_NS);
            $errors[] = "{$property}: rdfs:{$localName} harus menunjuk ke {$expectedIri}.";
        }
    }

    private function rangeIri(string $range): string
    {
        return match (true) {
            str_starts_with($range, 'xsd:') => OntologyMigrator::XSD_NS.substr($range, 4),
            str_starts_with($range, 'rdf:') => OntologyMigrator::RDF_NS.substr($range, 4),
            str_starts_with($range, 'rdfs:') => OntologyMigrator::RDFS_NS.substr($range, 5),
            default => OntologyMigrator::WS_NS.$range,
        };
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, array<string, bool>>  $types
     * @param  array{kind: string, value: string, datatype: ?string, language: ?string}  $object
     */
    private function validateObjectRange(array &$errors, array $types, array $object, string $range, string $property): void
    {
        if ($object['kind'] !== 'iri' || ! isset($types[$object['value']][OntologyMigrator::WS_NS.$range])) {
            $errors[] = "Range {$range} tidak terpenuhi untuk {$property}.";
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  array{kind: string, value: string, datatype: ?string, language: ?string}  $object
     */
    private function validateDataRange(array &$errors, array $object, string $range, string $property, string $subject): void
    {
        if ($object['kind'] !== 'literal') {
            $errors[] = "{$subject}: {$property} harus berupa literal.";

            return;
        }

        if ($range === 'rdfs:Literal') {
            return;
        }

        $expectedDatatype = match (true) {
            str_starts_with($range, 'xsd:') => OntologyMigrator::XSD_NS.substr($range, 4),
            $range === 'rdf:langString' => OntologyMigrator::RDF_NS.'langString',
            default => '',
        };

        if ($object['datatype'] !== $expectedDatatype) {
            $errors[] = "{$subject}: rentang {$property} harus {$range}.";

            return;
        }

        if ($range === 'rdf:langString' && $object['language'] !== 'id') {
            $errors[] = "{$subject}: {$property} harus memiliki tag bahasa @id.";
        }

        if ($range === 'xsd:decimal' && ! $this->isDecimal($object['value'])) {
            $errors[] = "{$subject}: {$property} bukan lexical xsd:decimal yang valid.";
        }

        if ($range === 'xsd:time' && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $object['value']) !== 1) {
            $errors[] = "{$subject}: {$property} bukan lexical xsd:time yang valid.";
        }

        if ($range === 'xsd:boolean' && ! in_array($object['value'], ['true', 'false', '1', '0'], true)) {
            $errors[] = "{$subject}: {$property} bukan lexical xsd:boolean yang valid.";
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, array<string, list<array{kind: string, value: string, datatype: ?string, language: ?string}>>>  $properties
     */
    private function validateCardinality(array &$errors, array $properties, string $propertyIri, string $property, int $maximum): void
    {
        foreach ($properties as $subject => $subjectProperties) {
            $values = $subjectProperties[$propertyIri] ?? [];
            if (count($values) > $maximum) {
                $errors[] = "{$subject}: {$property} melebihi kardinalitas maksimum {$maximum}.";
            }
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function validateSymmetry(array &$errors, array $triples, string $propertyIri, string $property): void
    {
        $edges = [];
        foreach ($triples as $triple) {
            if ($triple['predicate'] === $propertyIri && $triple['object']['kind'] === 'iri') {
                $edges[$triple['subject'].'|'.$triple['object']['value']] = true;
            }
        }

        foreach (array_keys($edges) as $edge) {
            [$subject, $object] = explode('|', $edge, 2);
            if (! isset($edges[$object.'|'.$subject])) {
                $errors[] = "{$property}: triple {$subject} -> {$object} tidak dimaterialisasi pada arah balik.";
            }
        }
    }

    private function localName(string $iri, string $namespace): string
    {
        return str_starts_with($iri, $namespace) ? substr($iri, strlen($namespace)) : '';
    }

    private function isDecimal(string $value): bool
    {
        return preg_match('/^[+-]?\d+(?:\.\d+)?$/', trim($value)) === 1;
    }
}
