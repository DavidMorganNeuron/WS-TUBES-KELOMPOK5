<?php

namespace App\Services\Ontology;

use InvalidArgumentException;

class TurtleParser
{
    /** @var array<string, string> */
    private array $prefixes = [];

    /**
     * @return list<array<string, string>>
     */
    public function parseFile(string $path): array
    {
        $resolvedPath = preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (! is_file($resolvedPath)) {
            throw new InvalidArgumentException("File Turtle tidak ditemukan: {$resolvedPath}");
        }

        return $this->parse((string) file_get_contents($resolvedPath));
    }

    /**
     * @return list<array<string, string>>
     */
    public function parse(string $turtle): array
    {
        $this->prefixes = [];
        $turtle = $this->removePrefixDirectives($turtle);

        $destinations = [];

        foreach ($this->splitTopLevel($turtle, '.') as $statement) {
            $statement = trim($statement);

            if ($statement === '' || str_starts_with($statement, '@')) {
                continue;
            }

            $record = $this->parseDestinationStatement($statement);

            if ($record === null) {
                continue;
            }

            $id = $record['idDestinasi'] ?? '';

            if ($id === '') {
                continue;
            }

            $destinations[$id] = isset($destinations[$id])
                ? $this->mergeRecords($destinations[$id], $record)
                : $record;
        }

        ksort($destinations);

        return array_values($destinations);
    }

    /**
     * @return array<string, string>|null
     */
    private function parseDestinationStatement(string $statement): ?array
    {
        if (! preg_match('/^(\S+)\s+/s', $statement, $matches)) {
            return null;
        }

        $subject = $matches[1];
        $body = substr($statement, strlen($matches[0]));
        $record = [];
        $isDestination = false;

        foreach ($this->splitTopLevel($body, ';') as $pair) {
            $pair = trim($pair);

            if ($pair === '' || ! preg_match('/^(\S+)\s+(.+)$/s', $pair, $pairMatches)) {
                continue;
            }

            $predicateToken = $pairMatches[1];
            $valueToken = trim($pairMatches[2]);

            if ($predicateToken === 'a' || $predicateToken === 'rdf:type') {
                $isDestination = str_contains($valueToken, 'DestinasiWisata');

                continue;
            }

            if (! $this->isOntologyPredicate($predicateToken)) {
                continue;
            }

            $property = $this->localName($predicateToken);

            foreach ($this->splitTopLevel($valueToken, ',') as $valueToken) {
                $this->addValue($record, $property, $this->parseValue($valueToken));
            }
        }

        if (! $isDestination) {
            return null;
        }

        $record['idDestinasi'] ??= $this->localName($subject);

        return $record;
    }

    /**
     * @param  array<string, string>  $record
     */
    private function addValue(array &$record, string $property, string $value): void
    {
        if (! array_key_exists($property, $record)) {
            $record[$property] = $value;

            return;
        }

        if ($value === '') {
            return;
        }

        if ($record[$property] === '') {
            $record[$property] = $value;

            return;
        }

        $values = explode(' | ', $record[$property]);

        if (in_array($value, $values, true)) {
            return;
        }

        $record[$property] .= ' | '.$value;
    }

    private function isOntologyPredicate(string $token): bool
    {
        if (str_starts_with($token, 'ws:')) {
            return true;
        }

        if (str_starts_with($token, '<')) {
            $iri = trim($token, '<>');
            $namespace = $this->prefixes['ws'] ?? 'http://pariwisatasumut.org/ontology/v2#';

            return str_starts_with($iri, $namespace);
        }

        return false;
    }

    private function localName(string $token): string
    {
        if (! str_starts_with($token, '<')) {
            $parts = explode(':', $token, 2);

            return $parts[1] ?? $token;
        }

        $iri = trim($token, '>');
        $iri = rtrim($iri, '#/');
        $position = max((int) strrpos($iri, '#'), (int) strrpos($iri, '/'));

        return $position > 0 ? substr($iri, $position + 1) : $iri;
    }

    private function parseValue(string $token): string
    {
        $token = trim($token);

        if ($token === '') {
            return '';
        }

        if (str_starts_with($token, '"""') || str_starts_with($token, "'''")) {
            $quote = substr($token, 0, 3);
            $end = strpos($token, $quote, 3);
            $content = $end === false ? substr($token, 3) : substr($token, 3, $end - 3);

            return $this->unescape($content);
        }

        if ($token[0] === '"' || $token[0] === "'") {
            return $this->unescape($this->quotedContent($token));
        }

        if ($token[0] === '<' && str_ends_with($token, '>')) {
            return substr($token, 1, -1);
        }

        if (preg_match('/^[+-]?\d+(\.\d+)?$/', $token) === 1) {
            return $token;
        }

        return $token;
    }

    private function quotedContent(string $token): string
    {
        $quote = $token[0];
        $length = strlen($token);
        $content = '';

        for ($index = 1; $index < $length; $index++) {
            $character = $token[$index];

            if ($character === '\\' && $index + 1 < $length) {
                $content .= $character.$token[$index + 1];
                $index++;

                continue;
            }

            if ($character === $quote) {
                break;
            }

            $content .= $character;
        }

        return $content;
    }

    private function unescape(string $value): string
    {
        return preg_replace_callback(
            '/\\\\(u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8}|.)/s',
            static function (array $matches): string {
                $sequence = $matches[1];

                if ($sequence[0] === 'u' || $sequence[0] === 'U') {
                    return mb_chr((int) hexdec(substr($sequence, 1)), 'UTF-8');
                }

                return match ($sequence) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0C",
                    default => $sequence,
                };
            },
            $value,
        ) ?? $value;
    }

    private function removePrefixDirectives(string $turtle): string
    {
        $turtle = preg_replace_callback(
            '/@prefix\s+([A-Za-z][\w-]*)?:\s*<([^>]+)>\s*\./i',
            function (array $matches): string {
                $this->prefixes[$matches[1]] = $matches[2];

                return '';
            },
            $turtle,
        ) ?? $turtle;

        $turtle = preg_replace_callback(
            '/\bPREFIX\s+([A-Za-z][\w-]*)?:\s*<([^>]+)>/i',
            function (array $matches): string {
                $this->prefixes[$matches[1]] = $matches[2];

                return '';
            },
            $turtle,
        ) ?? $turtle;

        return preg_replace('/@base\s+<[^>]+>\s*\./i', '', $turtle) ?? $turtle;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $input, string $delimiter): array
    {
        $parts = [];
        $buffer = '';
        $length = strlen($input);
        $quote = null;
        $tripleQuoted = false;
        $insideIri = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $input[$index];

            if ($quote !== null) {
                if ($character === '\\' && $index + 1 < $length) {
                    $buffer .= $character.$input[$index + 1];
                    $index++;

                    continue;
                }

                if ($tripleQuoted) {
                    $closing = str_repeat($quote, 3);

                    if (substr($input, $index, 3) === $closing) {
                        $buffer .= $closing;
                        $index += 2;
                        $quote = null;
                        $tripleQuoted = false;

                        continue;
                    }
                } elseif ($character === $quote) {
                    $quote = null;
                }

                $buffer .= $character;

                continue;
            }

            if ($insideIri) {
                $buffer .= $character;

                if ($character === '>') {
                    $insideIri = false;
                }

                continue;
            }

            if ($character === '<') {
                $insideIri = true;
                $buffer .= $character;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $tripleQuoted = substr($input, $index, 3) === str_repeat($character, 3);
                $quote = $character;
                $buffer .= $tripleQuoted ? str_repeat($character, 3) : $character;

                if ($tripleQuoted) {
                    $index += 2;
                }

                continue;
            }

            if ($character === $delimiter) {
                $nextCharacter = $input[$index + 1] ?? '';

                if ($delimiter === '.' && $nextCharacter !== '' && preg_match('/\s/', $nextCharacter) !== 1) {
                    $buffer .= $character;

                    continue;
                }

                $parts[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * @param  array<string, string>  $existing
     * @param  array<string, string>  $incoming
     * @return array<string, string>
     */
    private function mergeRecords(array $existing, array $incoming): array
    {
        foreach ($incoming as $property => $value) {
            if (! array_key_exists($property, $existing)) {
                $existing[$property] = $value;

                continue;
            }

            if ($value !== '' && ! str_contains($existing[$property], $value)) {
                $existing[$property] .= ' | '.$value;
            }
        }

        return $existing;
    }
}
