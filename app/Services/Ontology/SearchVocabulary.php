<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SearchVocabulary
{
    private ?string $memoizedVersion = null;

    /**
     * @var list<array{token: string, frequency: int}>|null
     */
    private ?array $memoizedVocabulary = null;

    public function __construct(
        private readonly SearchTokenizer $tokenizer,
        private readonly OntologyDataVersion $dataVersion,
    ) {}

    /**
     * @param  callable(): list<string>  $searchableTexts
     * @return list<array{token: string, frequency: int}>
     */
    public function tokens(callable $searchableTexts): array
    {
        try {
            $version = $this->dataVersion->current() ?? 'initial';
            $cacheKey = 'ontology.search-vocabulary.'.hash('sha256', $version);
        } catch (Throwable $exception) {
            Log::warning('Cache kosakata pencarian tidak tersedia; kosakata dihitung langsung.', [
                'exception' => $exception::class,
            ]);

            if ($this->memoizedVersion === 'initial' && $this->memoizedVocabulary !== null) {
                return $this->memoizedVocabulary;
            }

            return $this->memoize('initial', $this->build($searchableTexts()));
        }

        if ($this->memoizedVersion === $version && $this->memoizedVocabulary !== null) {
            return $this->memoizedVocabulary;
        }

        try {
            $cached = Cache::get($cacheKey);
        } catch (Throwable $exception) {
            Log::warning('Cache kosakata pencarian tidak tersedia; kosakata dihitung langsung.', [
                'exception' => $exception::class,
            ]);

            return $this->memoize($version, $this->build($searchableTexts()));
        }

        if ($this->isVocabulary($cached)) {
            return $this->memoize($version, $cached);
        }

        $vocabulary = $this->build($searchableTexts());
        try {
            Cache::put($cacheKey, $vocabulary, (int) config('ontology.cache_ttl', 600));
        } catch (Throwable $exception) {
            Log::warning('Kosakata pencarian tidak dapat disimpan ke cache.', [
                'exception' => $exception::class,
            ]);
        }

        return $this->memoize($version, $vocabulary);
    }

    /**
     * @param  list<array{token: string, frequency: int}>  $vocabulary
     * @return list<array{token: string, frequency: int}>
     */
    private function memoize(string $version, array $vocabulary): array
    {
        $this->memoizedVersion = $version;
        $this->memoizedVocabulary = $vocabulary;

        return $vocabulary;
    }

    /**
     * @param  list<string>  $texts
     * @return list<array{token: string, frequency: int}>
     */
    private function build(array $texts): array
    {
        $vocabulary = [];
        foreach ($texts as $text) {
            if (! is_string($text) || trim($text) === '' || trim($text) === '-') {
                continue;
            }

            foreach ($this->tokenizer->tokenizeIndexText($text) as $token) {
                $vocabulary[$token] = ($vocabulary[$token] ?? 0) + 1;
            }
        }

        ksort($vocabulary, SORT_STRING);

        $entries = [];
        foreach ($vocabulary as $token => $frequency) {
            $entries[] = ['token' => (string) $token, 'frequency' => $frequency];
        }

        return $entries;
    }

    private function isVocabulary(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if (! is_array($entry)
                || ! is_string($entry['token'] ?? null)
                || $entry['token'] === ''
                || ! is_int($entry['frequency'] ?? null)
                || $entry['frequency'] < 1) {
                return false;
            }
        }

        return true;
    }
}
