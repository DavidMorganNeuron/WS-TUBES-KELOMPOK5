<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final readonly class WikidataQuery
{
    /**
     * @param  list<string>  $qids
     */
    public function __construct(
        public string $query,
        public array $qids,
    ) {
        if (trim($query) === '' || $qids === []) {
            throw new InvalidArgumentException('Kueri Wikidata dan daftar QID wajib diisi.');
        }

        foreach ($qids as $qid) {
            if (! is_string($qid) || preg_match('/^Q[0-9]+$/', $qid) !== 1) {
                throw new InvalidArgumentException('Kueri Wikidata hanya boleh memakai QID yang valid.');
            }
        }
    }
}
