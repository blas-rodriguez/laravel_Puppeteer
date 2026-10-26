<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScrapeRunRequest;
use App\Jobs\RunScraper;
use App\Models\ScrapeRun;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScrapeRunController extends Controller
{
    public function index(): View
    {
        return view('scrapes.index', [
            'runs' => ScrapeRun::latest('id')->paginate(10),
            'total' => ScrapeRun::count(),
            'completed' => ScrapeRun::where('status', 'completed')->count(),
            'active' => ScrapeRun::whereIn('status', ['queued', 'running'])->count(),
            'presets' => config('scraper.presets'),
        ]);
    }

    public function store(StoreScrapeRunRequest $request): RedirectResponse
    {
        $run = ScrapeRun::create($request->validated());
        RunScraper::dispatch($run->id)->afterCommit();

        return to_route('scrapes.show', $run)->with('message', 'Extracción enviada a la cola.');
    }

    public function show(ScrapeRun $scrape): View
    {
        return view('scrapes.show', ['run' => $scrape]);
    }

    public function status(ScrapeRun $scrape): JsonResponse
    {
        return response()->json(['status' => $scrape->status, 'label' => $scrape->statusLabel()]);
    }

    public function export(ScrapeRun $scrape, string $format): StreamedResponse
    {
        abort_unless($scrape->status === 'completed' && in_array($format, ['json', 'csv'], true), 404);
        $records = $scrape->records ?? [];

        return response()->streamDownload(function () use ($scrape, $format, $records): void {
            if ($format === 'json') {
                echo json_encode([
                    'name' => $scrape->name, 'url' => $scrape->url, 'pages' => $scrape->pages,
                    'records' => $records,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                return;
            }

            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $headers = array_column($scrape->fields, 'name');
            fputcsv($stream, [...$headers, 'source_url', 'page_number'], escape: '');
            foreach ($records as $record) {
                $values = array_map(fn (string $key): string => (string) ($record['data'][$key] ?? ''), $headers);
                $values = array_map(function (string $value): string {
                    return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
                }, $values);
                fputcsv($stream, [...$values, $record['source_url'], $record['page_number']], escape: '');
            }
            fclose($stream);
        }, 'scrapelab-puppeteer-'.$scrape->id.'.'.$format, [
            'Content-Type' => $format === 'json' ? 'application/json; charset=UTF-8' : 'text/csv; charset=UTF-8',
        ]);
    }

    public function artifact(ScrapeRun $scrape, string $artifact): BinaryFileResponse
    {
        $filename = match ($artifact) {
            'screenshot' => 'screenshot.png',
            'trace' => 'trace.json',
            'network' => 'network.json',
            default => abort(404),
        };
        $path = storage_path('app/private/scrapes/'.$scrape->id.'/'.$filename);
        abort_unless(is_file($path), 404);

        return response()->download($path, 'scrapelab-puppeteer-'.$scrape->id.'-'.$filename);
    }
}
