<?php

namespace App\Services\Ontology;

use Illuminate\Container\Container;
use InvalidArgumentException;

final readonly class NorthSumatraCoverage
{
    /**
     * @param  list<array{min_latitude: float, max_latitude: float, min_longitude: float, max_longitude: float}>  $boxes
     */
    public function __construct(private array $boxes)
    {
        if ($this->boxes === []) {
            throw new InvalidArgumentException('Konfigurasi cakupan geografi tidak boleh kosong.');
        }
    }

    public static function fromConfig(): self
    {
        $configured = null;
        $container = Container::getInstance();
        if ($container !== null && $container->bound('config')) {
            $configured = $container->make('config')->get('ontology.coverage_boxes');
        }
        $configured ??= [
            'daratan_sumut' => [
                'min_latitude' => 0.5,
                'max_latitude' => 4.5,
                'min_longitude' => 97.0,
                'max_longitude' => 100.5,
            ],
            'kepulauan_batu' => [
                'min_latitude' => -0.5,
                'max_latitude' => 0.5,
                'min_longitude' => 97.9,
                'max_longitude' => 98.7,
            ],
        ];
        if (! is_array($configured) || $configured === []) {
            throw new InvalidArgumentException('Konfigurasi ontology.coverage_boxes wajib tersedia.');
        }

        $boxes = [];
        foreach ($configured as $name => $box) {
            if (! is_string($name) || ! is_array($box)
                || ! isset($box['min_latitude'], $box['max_latitude'], $box['min_longitude'], $box['max_longitude'])) {
                throw new InvalidArgumentException("Kotak cakupan {$name} tidak valid.");
            }

            $minLatitude = (float) $box['min_latitude'];
            $maxLatitude = (float) $box['max_latitude'];
            $minLongitude = (float) $box['min_longitude'];
            $maxLongitude = (float) $box['max_longitude'];
            if ($minLatitude > $maxLatitude || $minLongitude > $maxLongitude) {
                throw new InvalidArgumentException("Kotak cakupan {$name} memiliki batas terbalik.");
            }

            $boxes[] = [
                'min_latitude' => $minLatitude,
                'max_latitude' => $maxLatitude,
                'min_longitude' => $minLongitude,
                'max_longitude' => $maxLongitude,
            ];
        }

        return new self($boxes);
    }

    public function contains(float $latitude, float $longitude): bool
    {
        if (! is_finite($latitude) || ! is_finite($longitude)) {
            return false;
        }

        foreach ($this->boxes as $box) {
            if ($latitude >= $box['min_latitude'] && $latitude <= $box['max_latitude']
                && $longitude >= $box['min_longitude'] && $longitude <= $box['max_longitude']) {
                return true;
            }
        }

        return false;
    }

    public function summary(): string
    {
        $summaries = [];
        foreach ($this->boxes as $box) {
            $summaries[] = sprintf(
                'lintang %s–%s, bujur %s–%s',
                $this->formatCoordinate($box['min_latitude']),
                $this->formatCoordinate($box['max_latitude']),
                $this->formatCoordinate($box['min_longitude']),
                $this->formatCoordinate($box['max_longitude']),
            );
        }

        return implode('; ', $summaries);
    }

    private function formatCoordinate(float $coordinate): string
    {
        return rtrim(rtrim(number_format($coordinate, 4, '.', ''), '0'), '.');
    }
}
