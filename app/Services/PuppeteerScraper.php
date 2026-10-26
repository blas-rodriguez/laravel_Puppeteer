<?php

namespace App\Services;

use App\Models\ScrapeRun;
use RuntimeException;
use Symfony\Component\Process\Process;

class PuppeteerScraper
{
    public function run(ScrapeRun $run): array
    {
        $process = new Process([
            config('scraper.node_binary'), base_path('scripts/scrape.mjs'),
            storage_path('app/private/scrapes/'.$run->id),
        ], base_path());
        $process->setInput(json_encode($run->recipe(), JSON_THROW_ON_ERROR));
        $process->setTimeout(190);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(mb_substr($process->getErrorOutput() ?: 'Puppeteer terminó sin resultados.', 0, 4000));
        }

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($result) || ! isset($result['records'], $result['pages'], $result['duration_ms'])
            || ! is_array($result['records']) || count($result['records']) > 500) {
            throw new RuntimeException('La respuesta de Puppeteer no tiene el formato esperado.');
        }

        return $result;
    }
}
