<?php

declare(strict_types=1);

namespace App\Data;

final readonly class SeoData
{
    /**
     * @param  list<array{type: string, url: string, title: string, label: string}>  $alternates
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $imageUrl,
        public string $imageAlt,
        public string $ogType = 'website',
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
        public bool $noindex = false,
        public bool $isPlaceholderImage = false,
        public string $rdfaPrefixes = '',
        public array $alternates = [],
    ) {}
}
