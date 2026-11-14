<?php

declare(strict_types=1);

namespace App\Data;

final readonly class LinkedResource
{
    public function __construct(
        public string $iri,
        public string $label,
        public string $type,
        public string $labelProperty = 'rdfs:label',
        public ?string $regionType = null,
        public ?string $destinationId = null,
    ) {}
}
