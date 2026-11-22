<?php

declare(strict_types=1);

namespace App\Data;

final readonly class DestinationRdfDocument
{
    public function __construct(
        public string $body,
        public string $mediaType,
        public string $extension,
        public string $etag,
        public int $maxAge,
    ) {}
}
