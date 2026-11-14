<?php

use App\Http\Middleware\SecurityHeaders;
use App\Services\Ontology\OntologyUnavailableException;
use App\Services\Ontology\RdfFormatUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->reportable(function (OntologyUnavailableException $exception): bool {
            Log::warning('Layanan ontologi tidak tersedia.', [
                'message' => $exception->getMessage(),
                'dataset' => config('ontology.fuseki.dataset'),
            ]);

            return false;
        });
        $exceptions->render(function (OntologyUnavailableException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            return response()->view('errors.503', [
                'message' => $exception->getMessage(),
            ], 503);
        });
        $exceptions->render(function (RdfFormatUnavailableException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 501);
            }

            return response()->view('errors.501', [
                'message' => $exception->getMessage(),
            ], 501);
        });
    })->create();
