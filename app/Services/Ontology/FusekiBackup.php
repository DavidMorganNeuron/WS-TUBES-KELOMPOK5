<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class FusekiBackup
{
    public function __construct(private ?string $backupRoot = null) {}

    public function create(FusekiClient $client): string
    {
        $backupRoot = $this->backupRoot ?? storage_path('app/ontology-backups');
        if (! is_dir($backupRoot) && ! mkdir($backupRoot, 0755, true) && ! is_dir($backupRoot)) {
            throw new RuntimeException("Direktori backup tidak dapat dibuat: {$backupRoot}");
        }

        $backupPath = $backupRoot.DIRECTORY_SEPARATOR.now()->format('Ymd-His-u');
        if (! mkdir($backupPath, 0755)) {
            throw new RuntimeException("Direktori backup tidak dapat dibuat: {$backupPath}");
        }

        try {
            $this->writeFile($backupPath.DIRECTORY_SEPARATOR.'default.ttl', $client->downloadDefaultGraph());
            $namedGraphs = [];

            foreach ($client->namedGraphs() as $row) {
                $graph = $row['g'] ?? null;
                if (! is_string($graph) || $graph === '') {
                    throw OntologyUnavailableException::badResponse();
                }

                $file = 'graph-'.hash('sha256', $graph).'.ttl';
                $this->writeFile(
                    $backupPath.DIRECTORY_SEPARATOR.$file,
                    $client->downloadNamedGraph($graph),
                );
                $namedGraphs[] = ['iri' => $graph, 'file' => $file];
            }

            $manifest = json_encode([
                'created_at' => now()->toIso8601String(),
                'dataset' => config('ontology.fuseki.dataset'),
                'default_graph' => 'default.ttl',
                'named_graphs' => $namedGraphs,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->writeFile($backupPath.DIRECTORY_SEPARATOR.'manifest.json', $manifest);
        } catch (Throwable $exception) {
            File::deleteDirectory($backupPath);

            throw $exception;
        }

        $this->prune($backupRoot, $backupPath);

        return $backupPath;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("File backup tidak dapat ditulis: {$path}");
        }
    }

    private function prune(string $backupRoot, string $currentBackup): void
    {
        $backups = array_values(array_filter(
            File::directories($backupRoot),
            static fn (string $path): bool => preg_match('/^\d{8}-\d{6}-\d{6}$/', basename($path)) === 1,
        ));
        sort($backups, SORT_STRING);

        foreach (array_slice($backups, 0, max(0, count($backups) - 5)) as $oldBackup) {
            if ($oldBackup !== $currentBackup && ! File::deleteDirectory($oldBackup)) {
                throw new RuntimeException("Backup lama tidak dapat dihapus: {$oldBackup}");
            }
        }
    }
}
