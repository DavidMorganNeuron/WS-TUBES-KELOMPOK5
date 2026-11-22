<?php

namespace Tests\Unit;

use App\Services\Ontology\OntologyDataVersion;
use App\Services\Ontology\SearchPlanner;
use App\Services\Ontology\SearchTokenizer;
use App\Services\Ontology\SearchVocabulary;
use App\Services\Ontology\TypoCorrector;
use Tests\TestCase;

class SearchPlannerTest extends TestCase
{
    public function test_tokenizer_applies_shared_stop_words_and_rejects_bounded_input(): void
    {
        config()->set('ontology.search.stop_words', ['di', 'yang']);
        $tokenizer = new SearchTokenizer;

        $this->assertSame(['wisata', 'danau', 'toba'], $tokenizer->tokenize('Wisata di Danau Yang Toba'));
        $this->assertSame(['a'], $tokenizer->tokenize('A'));
        $this->assertSame([], $tokenizer->tokenize(str_repeat('a', 201)));
        $this->assertSame([], $tokenizer->tokenize(implode(' ', array_fill(0, 9, 'wisata'))));
    }

    public function test_typo_correction_handles_transposition_and_completes_known_final_prefix(): void
    {
        $result = (new TypoCorrector)->correct(
            ['dnau', 'tob'],
            [
                ['token' => 'danau', 'frequency' => 12],
                ['token' => 'toba', 'frequency' => 8],
                ['token' => 'tobing', 'frequency' => 2],
            ],
        );

        $this->assertSame(['danau', 'tob'], $result['tokens']);
        $this->assertSame(['danau', 'toba'], $result['display_tokens']);
    }

    public function test_numeric_vocabulary_tokens_remain_searchable(): void
    {
        $result = (new TypoCorrector)->correct(
            ['2024'],
            [['token' => '2024', 'frequency' => 1]],
        );

        $this->assertSame(['2024'], $result['tokens']);
    }

    public function test_repeated_letter_normalization_corrects_only_a_known_word(): void
    {
        $corrected = (new TypoCorrector)->correct(
            ['dannau'],
            [['token' => 'danau', 'frequency' => 3]],
        );
        $unrelated = (new TypoCorrector)->correct(
            ['zzzz'],
            [['token' => 'zebra', 'frequency' => 1]],
        );

        $this->assertSame(['danau'], $corrected['tokens']);
        $this->assertSame(['zzzz'], $unrelated['tokens']);
    }

    public function test_vocabulary_is_cached_without_repeating_text_extraction(): void
    {
        $vocabulary = new SearchVocabulary(new SearchTokenizer, new OntologyDataVersion);
        $calls = 0;
        $extractTexts = static function () use (&$calls): array {
            $calls++;

            return ['Danau Toba'];
        };

        $first = $vocabulary->tokens($extractTexts);
        $second = $vocabulary->tokens(static function (): array {
            throw new \RuntimeException('Cached vocabulary should not be rebuilt.');
        });

        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    public function test_exact_mode_skips_vocabulary_lookup_and_correction(): void
    {
        $planner = new SearchPlanner(
            new SearchTokenizer,
            new SearchVocabulary(new SearchTokenizer, new OntologyDataVersion),
            new TypoCorrector,
        );

        $plan = $planner->plan('dnau', static function (): array {
            throw new \RuntimeException('Exact mode should not build the vocabulary.');
        }, true);

        $this->assertSame(['dnau'], $plan->tokens);
        $this->assertNull($plan->correction);
        $this->assertSame([], $plan->suggestions);
    }
}
