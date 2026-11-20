<?php

namespace Tests\Unit;

use App\Services\Ontology\NorthSumatraCoverage;
use PHPUnit\Framework\TestCase;

class NorthSumatraCoverageTest extends TestCase
{
    public function test_geographic_coverage_accepts_only_configured_boxes(): void
    {
        $coverage = new NorthSumatraCoverage([
            ['min_latitude' => 0.5, 'max_latitude' => 4.5, 'min_longitude' => 97.0, 'max_longitude' => 100.5],
            ['min_latitude' => -0.5, 'max_latitude' => 0.5, 'min_longitude' => 97.9, 'max_longitude' => 98.7],
        ]);

        $this->assertTrue($coverage->contains(-0.0635, 98.27562));
        $this->assertFalse($coverage->contains(-1.0, 98.3));
        $this->assertFalse($coverage->contains(-0.95, 100.35));
        $this->assertFalse($coverage->contains(5.0, 98.0));
        $this->assertTrue($coverage->contains(-0.5, 97.9));
        $this->assertTrue($coverage->contains(0.7, 97.9));
        $this->assertTrue($coverage->contains(0.5, 100.5));
    }
}
