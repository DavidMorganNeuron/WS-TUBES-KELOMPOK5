<?php

namespace App\Console\Commands;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\OntologyDataVersion;
use App\Services\Ontology\OntologyUnavailableException;
use Illuminate\Console\Command;

final class OntologyWarm extends Command
{
    protected $signature = 'ontology:warm
        {--fresh : Hapus cache ontologi sebelum memanaskannya}';

    protected $description = 'Memanaskan cache ontologi yang dipakai halaman publik dan chatbot';

    public function handle(DestinationRepository $repository, OntologyDataVersion $dataVersion): int
    {
        if ($this->option('fresh')) {
            $dataVersion->refresh();
            $repository->forgetCache();
            $this->line('Cache ontologi lama dihapus.');
        }

        try {
            $destinations = $repository->all();
            $facets = $repository->facets();
            $markers = $repository->markers();
        } catch (OntologyUnavailableException $exception) {
            $this->error('Gagal memanaskan cache ontologi: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Cache ontologi siap dipakai.');
        $this->table(['Metrik', 'Nilai'], [
            ['Driver', (string) config('ontology.driver')],
            ['Total destinasi', number_format($destinations->count())],
            ['Destinasi berating', number_format($facets['rated_count'])],
            ['Rata-rata rating', $facets['average_rating'] === null
                ? (string) config('ontology.unknown_label')
                : number_format($facets['average_rating'], 2)],
            ['Destinasi berkoordinat', number_format($facets['with_coordinates'])],
            ['Marker peta', number_format(count($markers))],
            ['Kabupaten/kota', number_format(count($facets['kabupaten']))],
        ]);

        return self::SUCCESS;
    }
}
