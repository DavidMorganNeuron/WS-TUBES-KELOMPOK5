<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class MigrateV3CommandTest extends TestCase
{
    public function test_invalid_source_does_not_create_output_or_report(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wayfinder-migrate-invalid-'.bin2hex(random_bytes(6));
        File::makeDirectory($root, 0755, true);

        $source = $root.DIRECTORY_SEPARATOR.'wisata_sumut_v2-invalid.ttl';
        $output = $root.DIRECTORY_SEPARATOR.'wisata_sumut_v3.ttl';
        $report = $root.DIRECTORY_SEPARATOR.'laporan-anomali-data.md';
        $sourceContents = (string) file_get_contents(base_path('database/ontology/wisata_sumut_v2.ttl'));
        file_put_contents($source, str_replace('"-0.063500"', '"5.0"', $sourceContents));

        try {
            $this->artisan('ontology:migrate-v3', [
                '--source' => $source,
                '--output' => $output,
                '--report' => $report,
            ])->assertExitCode(1);

            $this->assertFileDoesNotExist($output);
            $this->assertFileDoesNotExist($report);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
