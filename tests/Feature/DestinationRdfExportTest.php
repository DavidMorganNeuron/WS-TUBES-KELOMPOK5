<?php

namespace Tests\Feature;

use App\Services\Ontology\FusekiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DestinationRdfExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ontology.driver', 'fuseki');
        config()->set('ontology.fuseki.url', 'http://localhost:3030');
        config()->set('ontology.fuseki.dataset', 'WayFinder');
        config()->set('ontology.fuseki.username', null);
        config()->set('ontology.fuseki.password', null);
        app()->forgetInstance(FusekiClient::class);
        Cache::flush();
    }

    public function test_it_exports_supported_rdf_formats_and_honors_conditional_requests(): void
    {
        $documents = [
            [
                'format' => 'ttl',
                'mediaType' => 'text/turtle',
                'body' => "@prefix ex: <http://example.test/> .\n<http://example.test/ws-001> ex:name \"Example\" .\n",
            ],
            [
                'format' => 'rdf',
                'mediaType' => 'application/rdf+xml',
                'body' => '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"><rdf:Description rdf:about="http://example.test/ws-002"><rdfs:label>Example</rdfs:label></rdf:Description></rdf:RDF>',
            ],
            [
                'format' => 'jsonld',
                'mediaType' => 'application/ld+json',
                'body' => '{"@graph":[{"@id":"http://example.test/ws-003","name":"Example"}]}',
            ],
        ];
        $sequence = Http::sequence();
        foreach ($documents as $document) {
            $sequence->push($document['body'], 200, ['Content-Type' => $document['mediaType']]);
        }
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => $sequence,
        ]);

        $etag = null;
        foreach ($documents as $index => $document) {
            $id = 'ws-00'.($index + 1);
            $response = $this->get('/destinations/'.$id.'/rdf.'.$document['format']);
            $response->assertOk()
                ->assertHeader('Content-Type', $document['mediaType'].'; charset=utf-8')
                ->assertHeader('Content-Disposition', 'attachment; filename="'.$id.'.'.$document['format'].'"')
                ->assertSee('Example', false);

            if ($document['format'] === 'ttl') {
                $etag = $response->headers->get('ETag');
            }
        }

        $this->assertIsString($etag);
        $this->get('/destinations/ws-001/rdf.ttl', ['If-None-Match' => $etag])
            ->assertNotModified();
        Http::assertSentCount(3);
    }

    public function test_empty_exports_return_not_found(): void
    {
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => Http::response(
                '@prefix ex: <http://example.test/> .'."\n",
                200,
                ['Content-Type' => 'text/turtle'],
            ),
        ]);

        $this->get('/destinations/ws-001/rdf.ttl')->assertNotFound();
    }

    public function test_fuseki_failures_are_reported_as_service_unavailable(): void
    {
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => Http::response(['error' => 'private-server-detail'], 503),
        ]);

        $this->get('/destinations/ws-001/rdf.ttl')
            ->assertStatus(503)
            ->assertSee('Layanan data destinasi sedang tidak tersedia.')
            ->assertDontSee('private-server-detail');
    }

    public function test_local_turtle_driver_exports_turtle_and_reports_other_formats_as_unavailable(): void
    {
        config()->set('ontology.driver', 'ttl');
        app()->forgetInstance(FusekiClient::class);
        Http::fake();

        $this->get('/destinations/ws-001/rdf.ttl')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/turtle; charset=utf-8')
            ->assertSee('ws-001')
            ->assertSee('owl:sameAs');

        $this->get('/destinations/ws-001/rdf.rdf')
            ->assertStatus(501)
            ->assertSee('Driver TTL lokal hanya mendukung ekspor Turtle.');

        Http::assertNothingSent();
    }
}
