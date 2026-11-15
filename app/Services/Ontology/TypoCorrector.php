<?php

declare(strict_types=1);

namespace App\Services\Ontology;

final class TypoCorrector
{
    /**
     * @param  list<string>  $tokens
     * @param  list<array{token: string, frequency: int}>  $vocabulary
     * @return array{tokens: list<string>, display_tokens: list<string>, suggestions: list<string>}
     */
    public function correct(array $tokens, array $vocabulary): array
    {
        [$byLength, $prefixesByLength, $prefixWords] = $this->indexes($vocabulary);
        $correctedTokens = [];
        $displayTokens = [];
        $suggestions = [];
        $changed = false;

        foreach ($tokens as $index => $token) {
            $isLast = $index === array_key_last($tokens);
            if (isset($byLength[mb_strlen($token, 'UTF-8')][$token]) || isset($prefixWords[$token])) {
                $correctedTokens[] = $token;
                $displayTokens[] = $token;

                continue;
            }

            $collapsed = preg_replace('/([\p{L}\p{N}])\1+/u', '$1', $token);
            if (is_string($collapsed) && $collapsed !== $token
                && isset($byLength[mb_strlen($collapsed, 'UTF-8')][$collapsed])) {
                $correctedTokens[] = $collapsed;
                $displayTokens[] = $collapsed;
                $changed = true;

                continue;
            }

            $maximumDistance = $this->maximumDistance($token);
            if ($maximumDistance === 0 && ! $isLast) {
                $correctedTokens[] = $token;
                $displayTokens[] = $token;
                $suggestions = [...$suggestions, ...$this->nearest($token, false, $byLength, $prefixesByLength)];

                continue;
            }

            $match = $this->bestMatch($token, $isLast, $maximumDistance, $byLength, $prefixesByLength);
            if ($match === null) {
                $correctedTokens[] = $token;
                $displayTokens[] = $token;
                $suggestions = [...$suggestions, ...$this->nearest($token, $isLast, $byLength, $prefixesByLength)];

                continue;
            }

            $correctedTokens[] = $match;
            $displayTokens[] = $match;
            $changed = true;
        }

        if ($changed && $tokens !== [] && $this->isExactPrefix($tokens[array_key_last($tokens)], $prefixWords)) {
            $lastToken = $tokens[array_key_last($tokens)];
            $completion = $this->bestPrefix($lastToken, $prefixWords);
            if ($completion !== null) {
                $displayTokens[array_key_last($displayTokens)] = $completion;
            }
        }

        return [
            'tokens' => $correctedTokens,
            'display_tokens' => $displayTokens,
            'suggestions' => array_slice(array_values(array_unique($suggestions)), 0, 3),
        ];
    }

    /**
     * @param  list<array{token: string, frequency: int}>  $vocabulary
     * @return array{
     *     0: array<int, array<string, int>>,
     *     1: array<int, array<string, int>>,
     *     2: array<string, list<array{word: string, frequency: int}>>
     * }
     */
    private function indexes(array $vocabulary): array
    {
        $byLength = [];
        $prefixesByLength = [];
        $prefixWords = [];

        foreach ($vocabulary as $entry) {
            $word = $entry['token'];
            $frequency = $entry['frequency'];
            $length = mb_strlen($word, 'UTF-8');
            $byLength[$length][$word] = $frequency;
            for ($prefixLength = 1; $prefixLength <= $length; $prefixLength++) {
                $prefix = mb_substr($word, 0, $prefixLength, 'UTF-8');
                $prefixesByLength[$prefixLength][$prefix] = max(
                    $prefixesByLength[$prefixLength][$prefix] ?? 0,
                    $frequency,
                );
                $prefixWords[$prefix][] = ['word' => $word, 'frequency' => $frequency];
            }
        }

        return [$byLength, $prefixesByLength, $prefixWords];
    }

    /**
     * @param  array<int, array<string, int>>  $byLength
     * @param  array<int, array<string, int>>  $prefixesByLength
     */
    private function bestMatch(string $token, bool $isLast, int $maximumDistance, array $byLength, array $prefixesByLength): ?string
    {
        $length = mb_strlen($token, 'UTF-8');
        $candidates = [];

        if ($isLast) {
            for ($candidateLength = max(1, $length - $maximumDistance); $candidateLength <= $length + $maximumDistance; $candidateLength++) {
                foreach ($prefixesByLength[$candidateLength] ?? [] as $candidate => $frequency) {
                    $candidate = (string) $candidate;
                    $distance = $this->distance($token, $candidate);
                    if ($distance <= $maximumDistance) {
                        $candidates[] = ['word' => $candidate, 'frequency' => $frequency, 'distance' => $distance];
                    }
                }
            }
        } else {
            for ($candidateLength = max(1, $length - $maximumDistance); $candidateLength <= $length + $maximumDistance; $candidateLength++) {
                foreach ($byLength[$candidateLength] ?? [] as $candidate => $frequency) {
                    $candidate = (string) $candidate;
                    $distance = $this->distance($token, $candidate);
                    if ($distance <= $maximumDistance) {
                        $candidates[] = ['word' => $candidate, 'frequency' => $frequency, 'distance' => $distance];
                    }
                }
            }
        }

        usort($candidates, fn (array $left, array $right): int => $this->compare($left, $right, $token));

        return $candidates[0]['word'] ?? null;
    }

    /**
     * @return list<string>
     */
    private function nearest(string $token, bool $isLast, array $byLength, array $prefixesByLength): array
    {
        $length = mb_strlen($token, 'UTF-8');
        $maximumLengthDifference = $this->maximumDistance($token) + 2;
        $candidates = [];
        $indexed = $isLast ? $prefixesByLength : $byLength;

        for ($candidateLength = max(1, $length - $maximumLengthDifference); $candidateLength <= $length + $maximumLengthDifference; $candidateLength++) {
            foreach ($indexed[$candidateLength] ?? [] as $candidate => $frequency) {
                $candidate = (string) $candidate;
                $distance = $this->distance($token, $candidate);
                $candidates[] = ['word' => $candidate, 'frequency' => $frequency, 'distance' => $distance];
            }
        }

        usort($candidates, fn (array $left, array $right): int => $this->compare($left, $right, $token));

        return array_values(array_map(
            static fn (array $candidate): string => $candidate['word'],
            array_slice($candidates, 0, 3),
        ));
    }

    /**
     * @param  array<string, list<array{word: string, frequency: int}>>  $prefixWords
     */
    private function isExactPrefix(string $token, array $prefixWords): bool
    {
        return isset($prefixWords[$token]);
    }

    /**
     * @param  array<string, list<array{word: string, frequency: int}>>  $prefixWords
     */
    private function bestPrefix(string $token, array $prefixWords): ?string
    {
        $words = $prefixWords[$token] ?? [];
        usort($words, static fn (array $left, array $right): int => (
            $right['frequency'] <=> $left['frequency']
        ) ?: (mb_strlen($left['word'], 'UTF-8') <=> mb_strlen($right['word'], 'UTF-8'))
            ?: strcmp($left['word'], $right['word']));

        return $words[0]['word'] ?? null;
    }

    /**
     * @param  array{word: string, frequency: int, distance: int}  $left
     * @param  array{word: string, frequency: int, distance: int}  $right
     */
    private function compare(array $left, array $right, string $token): int
    {
        $leftInitial = mb_substr($left['word'], 0, 1, 'UTF-8') === mb_substr($token, 0, 1, 'UTF-8');
        $rightInitial = mb_substr($right['word'], 0, 1, 'UTF-8') === mb_substr($token, 0, 1, 'UTF-8');

        return ($left['distance'] <=> $right['distance'])
            ?: ($right['frequency'] <=> $left['frequency'])
            ?: ($rightInitial <=> $leftInitial)
            ?: (abs(mb_strlen($token, 'UTF-8') - mb_strlen($left['word'], 'UTF-8'))
                <=> abs(mb_strlen($token, 'UTF-8') - mb_strlen($right['word'], 'UTF-8')))
            ?: strcmp($left['word'], $right['word']);
    }

    private function maximumDistance(string $token): int
    {
        $length = mb_strlen($token, 'UTF-8');

        return $length <= 3 ? 0 : ($length >= 6 ? 2 : 1);
    }

    private function distance(string $left, string $right): int
    {
        $leftCharacters = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightCharacters = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $leftLength = count($leftCharacters);
        $rightLength = count($rightCharacters);
        $previous = range(0, $rightLength);

        for ($leftIndex = 1; $leftIndex <= $leftLength; $leftIndex++) {
            $current = [$leftIndex];
            for ($rightIndex = 1; $rightIndex <= $rightLength; $rightIndex++) {
                $cost = $leftCharacters[$leftIndex - 1] === $rightCharacters[$rightIndex - 1] ? 0 : 1;
                $insertion = $current[$rightIndex - 1] + 1;
                $deletion = $previous[$rightIndex] + 1;
                $substitution = $previous[$rightIndex - 1] + $cost;
                $transposition = PHP_INT_MAX;

                if ($leftIndex > 1 && $rightIndex > 1
                    && $leftCharacters[$leftIndex - 1] === $rightCharacters[$rightIndex - 2]
                    && $leftCharacters[$leftIndex - 2] === $rightCharacters[$rightIndex - 1]) {
                    $transposition = $previous[$rightIndex - 2] + 1;
                }

                $current[$rightIndex] = min($insertion, $deletion, $substitution, $transposition);
            }
            $previous = $current;
        }

        return $previous[$rightLength];
    }
}
