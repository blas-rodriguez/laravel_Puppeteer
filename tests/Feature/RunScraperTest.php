<?php

namespace Tests\Feature;

use App\Jobs\RunScraper;
use App\Models\ScrapeRun;
use App\Services\PuppeteerScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RunScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_saves_results_and_is_idempotent(): void
    {
        $run = ScrapeRun::factory()->create();
        $result = ['records' => [['data' => ['cita' => 'Hola'], 'source_url' => $run->url, 'page_number' => 1]],
            'pages' => [['url' => $run->url, 'title' => 'Citas', 'count' => 1]], 'duration_ms' => 123];
        $scraper = $this->mock(PuppeteerScraper::class);
        $scraper->shouldReceive('run')->once()->andReturnUsing(function (ScrapeRun $claimed) use ($result): array {
            $this->assertSame('running', $claimed->status);

            return $result;
        });
        $job = new RunScraper($run->id);
        $job->handle($scraper);
        $job->handle($scraper);
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame($result['records'], $run->records);
        $this->assertSame(123, $run->duration_ms);
        $this->assertNotNull($run->finished_at);
    }

    public function test_failures_are_visible_without_persisting_partial_results(): void
    {
        $run = ScrapeRun::factory()->create();
        $scraper = $this->mock(PuppeteerScraper::class);
        $scraper->shouldReceive('run')->once()->andThrow(new RuntimeException('Selector inexistente'));
        (new RunScraper($run->id))->handle($scraper);
        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('Selector inexistente', $run->error);
        $this->assertNull($run->records);
        $this->get(route('scrapes.show', $run))->assertOk()->assertSee('Selector inexistente');
    }
}
