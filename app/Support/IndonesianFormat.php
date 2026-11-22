<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

final class IndonesianFormat
{
    public static function number(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return self::unknown();
        }

        if ($decimals < 0 || ! is_finite((float) $value)) {
            throw new InvalidArgumentException('Angka dan jumlah desimal harus valid.');
        }

        return number_format((float) $value, $decimals, ',', '.');
    }

    public static function currency(int|float|null $amount): string
    {
        if ($amount === null) {
            return self::unknown();
        }

        if ($amount <= 0) {
            return 'Gratis';
        }

        return 'Rp '.self::number($amount);
    }

    public static function rating(?float $value): string
    {
        return self::number($value, 1);
    }

    public static function percentage(?float $value, int $decimals = 1): string
    {
        if ($value === null) {
            return self::unknown();
        }

        return self::number($value, $decimals).'%';
    }

    public static function date(?DateTimeInterface $value): string
    {
        if ($value === null) {
            return self::unknown();
        }

        return Carbon::instance($value)->locale('id')->translatedFormat('j F Y');
    }

    public static function year(?int $value): string
    {
        return $value === null ? self::unknown() : (string) $value;
    }

    private static function unknown(): string
    {
        return (string) config('ontology.unknown_label');
    }
}
