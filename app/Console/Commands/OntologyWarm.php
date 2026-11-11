<?php

namespace App\Console\Commands;

use App\Services\Ontology\DestinationRepository;
use Illuminate\Console\Command;
use Throwable;

class OntologyWarm extends Command
{
    protected $signature = 'ontology:warm
        {--fresh : Hapus cache ontologi sebelum memanaskannya}';

    protected $description = 'Memanaskan cache ontologi yang dipakai halaman publik dan chatbot';

    public function handle(DestinationRepository $repository): int
    {
        if ($this->option('fresh')) {
            $repository->forgetCache();
            $this->line('Cache ontologi lama dihapus.');
        }

        try {
            $destinations = $repository->all();
            $facets = $repository->facets();
            $markers = $repository->markers();
        } catch (Throwable $exception) {
            $this->error('Gagal memanaskan cache ontologi: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Cache ontologi siap dipakai.');
        $this->table(['Metrik', 'Nilai'], [
            ['Driver', (string) config('ontology.driver')],
            ['Total destinasi', number_format($destinations->count())],
            ['Destinasi berating', number_format($facets['rated_count'])],
            ['Rata-rata rating', $facets['average_rating'] === null ? '—' : number_format($facets['average_rating'], 2)],
            ['Destinasi berkoordinat', number_format($facets['with_coordinates'])],
            ['Marker peta', number_format(count($markers))],
            ['Kabupaten/kota', number_format(count($facets['kabupaten']))],
        ]);

        return self::SUCCESS;
    }
}
