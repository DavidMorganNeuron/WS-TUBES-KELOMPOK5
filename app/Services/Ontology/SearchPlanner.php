<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\SearchPlan;

final class SearchPlanner
{
    public function __construct(
        private readonly SearchTokenizer $tokenizer,
        private readonly SearchVocabulary $vocabulary,
        private readonly TypoCorrector $typoCorrector,
    ) {}

    /**
     * @param  callable(): list<string>  $searchableTexts
     */
    public function plan(string $query, callable $searchableTexts, bool $exact = false): SearchPlan
    {
        $originalQuery = trim($query);
        $tokens = $this->tokenizer->tokenize($originalQuery);
        if ($originalQuery === '' || $tokens === [] || $exact) {
            return new SearchPlan(
                originalQuery: $originalQuery,
                correctedQuery: implode(' ', $tokens),
                originalTokens: $tokens,
                tokens: $tokens,
            );
        }

        $vocabulary = $this->vocabulary->tokens($searchableTexts);
        $correction = $this->typoCorrector->correct($tokens, $vocabulary);
        $correctedQuery = implode(' ', $correction['display_tokens']);
        $originalNormalized = implode(' ', $tokens);
        $correctionMetadata = $correctedQuery !== $originalNormalized
            ? ['original' => $originalQuery, 'corrected' => $correctedQuery]
            : null;

        return new SearchPlan(
            originalQuery: $originalQuery,
            correctedQuery: $correctedQuery,
            originalTokens: $tokens,
            tokens: $correction['tokens'],
            correction: $correctionMetadata,
            suggestions: $correction['suggestions'],
        );
    }
}
