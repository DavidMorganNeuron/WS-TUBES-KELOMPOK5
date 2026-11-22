<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final class WikidataUserAgent
{
    public static function resolve(string $contact, string $fallback): string
    {
        $contact = trim($contact);
        $contact = $contact === '' ? trim($fallback) : $contact;

        if ($contact === '' || preg_match('/[\x00-\x20\x7f]/', $contact) === 1) {
            throw new InvalidArgumentException('Kontak User-Agent Wikidata tidak valid.');
        }

        if (filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
            $parts = parse_url($contact);
            if (filter_var($contact, FILTER_VALIDATE_URL) === false
                || ! is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || ! is_string($parts['host'] ?? null)
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment'])) {
                throw new InvalidArgumentException('Kontak User-Agent Wikidata harus berupa email atau URL HTTPS.');
            }

            $path = rtrim((string) ($parts['path'] ?? ''), '/');
            if (str_ends_with(strtolower($path), '.git')) {
                $path = substr($path, 0, -4);
            }
            $contact = 'https://'.strtolower($parts['host']).$path;
        }

        return 'WayFinder/1.0 ('.$contact.')';
    }
}
