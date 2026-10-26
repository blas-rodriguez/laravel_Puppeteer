<?php

namespace Tests\Feature;

use App\Jobs\RunScraper;
use App\Models\ScrapeRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScrapeRunTest extends TestCase
{
    use RefreshDatabase;

    private function recipe(): array
    {
        return [
            'name' => 'Prueba', 'url' => 'https://quotes.toscrape.com/js/',
            'item_selector' => '.quote', 'next_selector' => '.next a', 'max_pages' => 2,
            'fields' => [['name' => 'texto', 'selector' => '.text']],
        ];
    }

    public function test_a_valid_recipe_is_persisted_and_queued(): void
    {
        Queue::fake();
        $response = $this->post(route('scrapes.store'), $this->recipe());
        $run = ScrapeRun::sole();
        $response->assertRedirect(route('scrapes.show', $run));
        $this->assertSame('queued', $run->status);
        Queue::assertPushed(RunScraper::class, fn ($job) => $job->runId === $run->id);
        $this->get(route('scrapes.show', $run))->assertOk()->assertSee('Tu extracción está en marcha');
    }

    public function test_json_editor_input_is_decoded(): void
    {
        Queue::fake();
        $recipe = $this->recipe();
        $recipe['fields'] = json_encode($recipe['fields']);
        $this->post(route('scrapes.store'), $recipe)->assertSessionHasNoErrors();
        $this->assertSame('texto', ScrapeRun::sole()->fields[0]['name']);
    }

    public function test_invalid_recipes_never_start_a_job(): void
    {
        Queue::fake();
        foreach (['http://127.0.0.1', 'http://localhost', 'http://10.0.0.1', 'file:///etc/passwd', 'https://user:pass@example.com', 'http://example.com:3306'] as $url) {
            $this->postJson(route('scrapes.store'), [...$this->recipe(), 'url' => $url])->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        $this->postJson(route('scrapes.store'), [...$this->recipe(), 'max_pages' => 6, 'fields' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['max_pages', 'fields']);
        $this->assertDatabaseCount('scrape_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_field_names_must_be_unique_and_cannot_inject_extra_configuration(): void
    {
        Queue::fake();
        $this->postJson(route('scrapes.store'), [...$this->recipe(), 'fields' => [
            ['name' => 'same', 'selector' => '.a'], ['name' => 'same', 'selector' => '.b'],
        ]])->assertUnprocessable();
        $this->post(route('scrapes.store'), [...$this->recipe(), 'status' => 'completed', 'records' => [['data' => 'injected']]])
            ->assertSessionHasNoErrors();
        $run = ScrapeRun::sole();
        $this->assertSame('queued', $run->status);
        $this->assertNull($run->records);
    }

    public function test_results_are_escaped_and_exports_preserve_data_and_neutralize_formulas(): void
    {
        $run = ScrapeRun::factory()->create([
            'status' => 'completed', 'fields' => [['name' => 'value', 'selector' => 'p']],
            'records' => [['data' => ['value' => '=1+1'], 'source_url' => 'https://example.com', 'page_number' => 1],
                ['data' => ['value' => '<script>alert(1)</script>'], 'source_url' => 'https://example.com', 'page_number' => 1]],
            'pages' => [],
        ]);
        $this->get(route('scrapes.show', $run))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $csv = $this->get(route('scrapes.export', [$run, 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $json = $this->get(route('scrapes.export', [$run, 'json']))->assertOk()->streamedContent();
        $this->assertSame('=1+1', json_decode($json, true)['records'][0]['data']['value']);
        $this->get(route('scrapes.export', [$run, 'xml']))->assertNotFound();
    }

    public function test_pending_exports_and_unavailable_artifacts_return_404(): void
    {
        $run = ScrapeRun::factory()->create();
        $this->get(route('scrapes.export', [$run, 'csv']))->assertNotFound();
        $this->get(route('scrapes.artifact', [$run, 'unknown']))->assertNotFound();
        $this->get(route('scrapes.status', $run))->assertExactJson(['status' => 'queued', 'label' => 'En cola']);
    }
}
