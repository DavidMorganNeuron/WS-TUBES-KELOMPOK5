<?php

namespace Tests\Unit;

use App\Services\Ontology\DestinationQueryBuilder;
use App\Services\Ontology\SparqlEscaper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SparqlQueryBuilderTest extends TestCase
{
    public function test_untrusted_filter_values_are_whitelisted_or_escaped_before_query_construction(): void
    {
        $builder = new DestinationQueryBuilder(new SparqlEscaper, ['Kabupaten Toba']);
        $injection = 'Danau" } UNION { ?s ?p ?o';

        $query = $builder->ids([
            'q' => $injection,
            'jenis' => 'Alam" } UNION { ?s ?p ?o',
            'kabupaten' => 'Kabupaten Toba" } UNION { ?s ?p ?o',
            'sort' => 'rating DESC(?s)',
        ]);

        $this->assertStringNotContainsString($injection, $query);
        $this->assertStringNotContainsString('Alam" } UNION', $query);
        $this->assertStringNotContainsString('Kabupaten Toba" } UNION', $query);
        $this->assertStringContainsString('FILTER(false)', $query);
        $this->assertStringContainsString('ORDER BY DESC(BOUND(?ratingSort))', $query);
    }

    public function test_canonical_region_is_encoded_as_an_exact_literal_and_limits_are_normalized(): void
    {
        $builder = new DestinationQueryBuilder(new SparqlEscaper, ['Kabupaten Toba']);

        $query = $builder->ids(['kabupaten' => 'Kabupaten Toba'], 25, -5);

        $this->assertStringContainsString('FILTER(STR(?districtLabel) = "Kabupaten Toba")', $query);
        $this->assertStringContainsString('LIMIT 25 OFFSET 0', $query);
    }

    public function test_destination_iris_reject_unsafe_identifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SparqlEscaper)->destinationIri('ws-001> } UNION { ?s');
    }
}
