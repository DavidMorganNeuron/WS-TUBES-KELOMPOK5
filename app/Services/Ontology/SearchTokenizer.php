<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use Illuminate\Support\Str;

final class SearchTokenizer
{
    private const DEFAULT_STOP_WORDS = ['di', 'yang', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dalam', 'serta', 'atau'];

    /**
     * @return list<string>
     */
    public function tokenize(string $query): array
    {
        if (mb_strlen($query, 'UTF-8') > 200) {
            return [];
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) > 8) {
            return [];
        }

        $stopWords = $this->stopWords();
        $normalized = [];
        foreach ($tokens as $token) {
            $token = Str::lower($token);
            if (mb_strlen($token, 'UTF-8') > 40
                || preg_match('/^[\p{L}\p{N}]+$/u', $token) !== 1) {
                return [];
            }

            if (in_array($token, $stopWords, true)) {
                continue;
            }

            $normalized[] = $token;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function tokenizeIndexText(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = $this->stopWords();

        return array_values(array_filter(
            array_map(static fn (string $token): string => Str::lower($token), $tokens),
            static fn (string $token): bool => mb_strlen($token, 'UTF-8') <= 40
                && preg_match('/^[\p{L}\p{N}]+$/u', $token) === 1
                && ! in_array($token, $stopWords, true),
        ));
    }

    /**
     * @return list<string>
     */
    private function stopWords(): array
    {
        $stopWords = function_exists('app') && app()->bound('config')
            ? config('ontology.search.stop_words', self::DEFAULT_STOP_WORDS)
            : self::DEFAULT_STOP_WORDS;
        if (! is_array($stopWords)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $word): string => is_string($word) ? Str::lower(trim($word)) : '', $stopWords),
            static fn (string $word): bool => $word !== '' && preg_match('/^[\p{L}\p{N}]+$/u', $word) === 1,
        ));
    }
}
