<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use RuntimeException;
use Throwable;

final class OntologyUnavailableException extends RuntimeException
{
    public static function serverUnavailable(?Throwable $previous = null): self
    {
        return new self('Layanan data destinasi sedang tidak tersedia. Periksa koneksi Fuseki.', 0, $previous);
    }

    public static function datasetMissing(string $dataset, ?Throwable $previous = null): self
    {
        return new self("Dataset Fuseki \"{$dataset}\" tidak ditemukan.", 0, $previous);
    }

    public static function accessDenied(int $status, ?Throwable $previous = null): self
    {
        return new self("Akses Fuseki ditolak (HTTP {$status}). Periksa kredensial yang dikonfigurasi.", 0, $previous);
    }

    public static function requestRejected(int $status, ?Throwable $previous = null): self
    {
        return new self("Fuseki menolak permintaan (HTTP {$status}). Periksa query atau data Turtle.", 0, $previous);
    }

    public static function badResponse(?Throwable $previous = null): self
    {
        return new self('Fuseki mengembalikan respons yang tidak dapat diproses.', 0, $previous);
    }
}
