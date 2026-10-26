<?php

namespace App\Jobs;

use App\Models\ScrapeRun;
use App\Services\PuppeteerScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunScraper implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 210;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId) {}

    /**
     * Execute the job.
     */
    public function handle(PuppeteerScraper $scraper): void
    {
        $claimed = ScrapeRun::whereKey($this->runId)->where('status', 'queued')
            ->update(['status' => 'running', 'started_at' => now()]);
        if (! $claimed) {
            return;
        }

        $run = ScrapeRun::findOrFail($this->runId);
        try {
            $result = $scraper->run($run);
            $run->update([
                'status' => 'completed', 'records' => $result['records'], 'pages' => $result['pages'],
                'duration_ms' => $result['duration_ms'], 'finished_at' => now(), 'error' => null,
            ]);
        } catch (Throwable $exception) {
            $this->failed($exception);
            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ScrapeRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed', 'error' => mb_substr($exception?->getMessage() ?? 'El trabajo fue interrumpido.', 0, 4000),
            'finished_at' => now(),
        ]);
    }
}
