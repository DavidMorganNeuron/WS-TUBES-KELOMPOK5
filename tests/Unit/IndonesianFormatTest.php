<?php

namespace Tests\Unit;

use App\Support\IndonesianFormat;
use Carbon\Carbon;
use Tests\TestCase;

class IndonesianFormatTest extends TestCase
{
    public function test_it_formats_numbers_and_currency_for_indonesian_readers(): void
    {
        $this->assertSame('1.234.567,50', IndonesianFormat::number(1234567.5, 2));
        $this->assertSame('Rp 15.000', IndonesianFormat::currency(15000));
        $this->assertSame('Gratis', IndonesianFormat::currency(0));
    }

    public function test_it_formats_ratings_percentages_and_years(): void
    {
        $this->assertSame('4,5', IndonesianFormat::rating(4.5));
        $this->assertSame('12,5%', IndonesianFormat::percentage(12.5));
        $this->assertSame('2026', IndonesianFormat::year(2026));
    }

    public function test_it_localizes_dates_and_shows_the_unknown_label_for_missing_values(): void
    {
        $date = Carbon::create(2026, 1, 5, 0, 0, 0, 'Asia/Jakarta');

        $this->assertSame('5 Januari 2026', IndonesianFormat::date($date));
        $this->assertSame(config('ontology.unknown_label'), IndonesianFormat::number(null));
        $this->assertSame(config('ontology.unknown_label'), IndonesianFormat::currency(null));
        $this->assertSame(config('ontology.unknown_label'), IndonesianFormat::rating(null));
        $this->assertSame(config('ontology.unknown_label'), IndonesianFormat::percentage(null));
        $this->assertSame(config('ontology.unknown_label'), IndonesianFormat::date(null));
        $this->assertSame(config('ontology.unknown_label'), IndonesianFormat::year(null));
    }
}
