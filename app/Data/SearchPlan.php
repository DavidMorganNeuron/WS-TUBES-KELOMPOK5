<?php

declare(strict_types=1);

namespace App\Data;

final readonly class SearchPlan
{
    /**
     * @param  list<string>  $originalTokens
     * @param  list<string>  $tokens
     * @param  array{original: string, corrected: string}|null  $correction
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public string $originalQuery,
        public string $correctedQuery,
        public array $originalTokens,
        public array $tokens,
        public ?array $correction = null,
        public array $suggestions = [],
    ) {}
}
