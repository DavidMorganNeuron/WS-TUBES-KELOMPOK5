<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

final class WikidataClient
{
    public const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly string $userAgent,
        private readonly int $requestTimeout,
        private readonly int $refreshTimeout,
        private readonly int $maxRetries,
        private readonly int $maxRetryAfter,
        private readonly int $maxResponseBytes,
        private readonly int $getQueryMaxBytes,
        private readonly int $cacheTtl,
    ) {}

    /**
     * @return list<array<string, array{type: string, value: string, datatype?: string, 'xml:lang'?: string}>>
     */
    public function select(WikidataQuery $query, bool $refresh = false): array
    {
        $cacheKey = 'wikidata:sparql:'.hash('sha256', $query->query);
        if (! $refresh) {
            $cachedRows = Cache::get($cacheKey);
            if (is_array($cachedRows)) {
                return $cachedRows;
            }
        }

        $response = $this->request($query->query, $refresh);
        if (! $response->successful()) {
            throw new RuntimeException("Wikidata Query Service merespons HTTP {$response->status()}.");
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! in_array($contentType, ['application/sparql-results+json', 'application/json'], true)) {
            throw new RuntimeException('Wikidata Query Service tidak mengembalikan format JSON SPARQL.');
        }

        $body = $response->body();
        if (strlen($body) > $this->maxResponseBytes) {
            throw new RuntimeException('Respons Wikidata melebihi batas ukuran yang diizinkan.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Respons Wikidata bukan JSON yang valid.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Respons Wikidata harus berupa objek JSON.');
        }

        $bindings = $decoded['results']['bindings'] ?? null;
        if (! is_array($bindings) || ! array_is_list($bindings)) {
            throw new RuntimeException('Struktur hasil SPARQL Wikidata tidak valid.');
        }

        $rows = [];
        foreach ($bindings as $binding) {
            if (! is_array($binding)) {
                throw new RuntimeException('Binding SPARQL Wikidata tidak valid.');
            }

            foreach ($binding as $property => $value) {
                if (! is_string($property) || ! is_array($value)
                    || ! is_string($value['type'] ?? null)
                    || ! in_array($value['type'], ['uri', 'literal', 'typed-literal', 'bnode'], true)
                    || ! is_string($value['value'] ?? null)) {
                    throw new RuntimeException('Nilai binding SPARQL Wikidata tidak valid.');
                }
            }

            $rows[] = $binding;
        }

        if ($this->cacheTtl > 0) {
            Cache::put($cacheKey, $rows, now()->addSeconds($this->cacheTtl));
        }

        return $rows;
    }

    private function request(string $query, bool $refresh): Response
    {
        $timeout = $refresh ? $this->refreshTimeout : $this->requestTimeout;
        $attempt = 0;

        while (true) {
            try {
                $request = Http::withHeaders([
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => $this->userAgent,
                ])->connectTimeout(min(5, $timeout))->timeout($timeout);

                $response = strlen($query) <= $this->getQueryMaxBytes
                    ? $request->get(self::ENDPOINT, ['query' => $query])
                    : $request->asForm()->post(self::ENDPOINT, ['query' => $query]);
            } catch (ConnectionException $exception) {
                if ($attempt >= $this->maxRetries) {
                    throw new RuntimeException('Tidak dapat terhubung ke Wikidata Query Service.', previous: $exception);
                }

                $this->pause(min(2 ** $attempt, $this->maxRetryAfter));
                $attempt++;

                continue;
            }

            if (! in_array($response->status(), [429, 500, 502, 503, 504], true)
                || $attempt >= $this->maxRetries) {
                return $response;
            }

            $delay = $response->status() === 429
                ? $this->retryAfter($response)
                : min(2 ** $attempt, $this->maxRetryAfter);
            $this->pause($delay);
            $attempt++;
        }
    }

    private function retryAfter(Response $response): int
    {
        $value = trim((string) $response->header('Retry-After'));
        if (preg_match('/^[0-9]+$/', $value) === 1) {
            return min((int) $value, $this->maxRetryAfter);
        }

        if ($value !== '') {
            try {
                $seconds = (new DateTimeImmutable($value))->getTimestamp() - time();

                return min(max(0, $seconds), $this->maxRetryAfter);
            } catch (\Exception) {
                // Use the bounded backoff for an unparseable header.
            }
        }

        return min(1, $this->maxRetryAfter);
    }

    private function pause(int $seconds): void
    {
        if ($seconds > 0) {
            usleep($seconds * 1_000_000);
        }
    }
}
