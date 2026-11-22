<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Ontology\DestinationRdfExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DestinationRdfController extends Controller
{
    public function __invoke(Request $request, string $id, string $format, DestinationRdfExporter $exporter): Response
    {
        $formats = config('ontology.rdf_export.formats', []);
        if (preg_match('/^ws-\d{3}$/', $id) !== 1 || ! is_array($formats) || ! isset($formats[$format])) {
            return $this->notFound($request);
        }

        $document = $exporter->export($id, $format);
        if ($document === null) {
            return $this->notFound($request);
        }

        $response = response($document->body, 200, [
            'Content-Type' => $document->mediaType.'; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$id.'.'.$document->extension.'"',
            'Cache-Control' => 'public, max-age='.$document->maxAge,
            'ETag' => $document->etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->isNotModified($request);

        return $response;
    }

    private function notFound(Request $request): Response
    {
        $message = 'Data RDF untuk destinasi tersebut tidak ditemukan.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 404);
        }

        return response()->view('errors.404', ['message' => $message], 404);
    }
}
