<?php

declare(strict_types=1);

namespace App\Services\Ontology;

final readonly class OpeningHoursResolver
{
    public function __construct(private string $unknownLabel) {}

    public function resolve(
        ?string $description,
        ?string $opening,
        ?string $closing,
        bool|string|null $alwaysOpen,
    ): ?string {
        $description = trim($description ?? '');
        if ($description !== '' && $description !== '-' && $description !== $this->unknownLabel) {
            return $description;
        }

        if ($this->isValidTime($opening) && $this->isValidTime($closing)) {
            return $this->formatTime($opening).' - '.$this->formatTime($closing);
        }

        if ($alwaysOpen === true || (is_string($alwaysOpen) && in_array(trim($alwaysOpen), ['true', '1'], true))) {
            return 'Selalu Buka';
        }

        return null;
    }

    private function isValidTime(?string $time): bool
    {
        return is_string($time)
            && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', $time) === 1;
    }

    private function formatTime(string $time): string
    {
        return str_replace(':', '.', substr($time, 0, 5));
    }
}
