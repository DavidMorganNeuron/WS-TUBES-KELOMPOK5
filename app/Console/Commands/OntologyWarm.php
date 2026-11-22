<?php

namespace App\Console\Commands;

use App\Services\Ontology\DestinationRepository;
<<<<<<< HEAD
use App\Services\Ontology\OntologyDataVersion;
use App\Services\Ontology\OntologyUnavailableException;
use Illuminate\Console\Command;

final class OntologyWarm extends Command
=======
use Illuminate\Console\Command;
use Throwable;

class OntologyWarm extends Command
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
{
    protected $signature = 'ontology:warm
        {--fresh : Hapus cache ontologi sebelum memanaskannya}';

    protected $description = 'Memanaskan cache ontologi yang dipakai halaman publik dan chatbot';

<<<<<<< HEAD
    public function handle(DestinationRepository $repository, OntologyDataVersion $dataVersion): int
    {
        if ($this->option('fresh')) {
            $dataVersion->refresh();
=======
    public function handle(DestinationRepository $repository): int
    {
        if ($this->option('fresh')) {
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            $repository->forgetCache();
            $this->line('Cache ontologi lama dihapus.');
        }

        try {
            $destinations = $repository->all();
            $facets = $repository->facets();
            $markers = $repository->markers();
<<<<<<< HEAD
        } catch (OntologyUnavailableException $exception) {
=======
        } catch (Throwable $exception) {
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            $this->error('Gagal memanaskan cache ontologi: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Cache ontologi siap dipakai.');
        $this->table(['Metrik', 'Nilai'], [
            ['Driver', (string) config('ontology.driver')],
            ['Total destinasi', number_format($destinations->count())],
            ['Destinasi berating', number_format($facets['rated_count'])],
<<<<<<< HEAD
            ['Rata-rata rating', $facets['average_rating'] === null
                ? (string) config('ontology.unknown_label')
                : number_format($facets['average_rating'], 2)],
=======
            ['Rata-rata rating', $facets['average_rating'] === null ? '—' : number_format($facets['average_rating'], 2)],
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            ['Destinasi berkoordinat', number_format($facets['with_coordinates'])],
            ['Marker peta', number_format(count($markers))],
            ['Kabupaten/kota', number_format(count($facets['kabupaten']))],
        ]);

        return self::SUCCESS;
    }
}
