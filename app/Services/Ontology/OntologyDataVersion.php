<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use Illuminate\Support\Facades\Cache;

final class OntologyDataVersion
{
    public const CACHE_KEY = 'ontology:data-version';

    public function refresh(string $sourceFingerprint = ''): string
    {
        $timestamp = now()->format('YmdHis.u');
        $version = $timestamp.'-'.substr(hash('sha256', $sourceFingerprint.'|'.$timestamp), 0, 16);
        Cache::forever(self::CACHE_KEY, $version);

        return $version;
    }

    public function current(): ?string
    {
        $version = Cache::get(self::CACHE_KEY);

        return is_string($version) ? $version : null;
    }
}
