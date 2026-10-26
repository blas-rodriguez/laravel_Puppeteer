<?php

use App\Http\Controllers\ScrapeRunController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScrapeRunController::class, 'index'])->name('scrapes.index');
Route::post('/scrapes', [ScrapeRunController::class, 'store'])->middleware('throttle:10,1')->name('scrapes.store');
Route::get('/scrapes/{scrape}', [ScrapeRunController::class, 'show'])->name('scrapes.show');
Route::get('/scrapes/{scrape}/status', [ScrapeRunController::class, 'status'])->name('scrapes.status');
Route::get('/scrapes/{scrape}/export/{format}', [ScrapeRunController::class, 'export'])->name('scrapes.export');
Route::get('/scrapes/{scrape}/artifacts/{artifact}', [ScrapeRunController::class, 'artifact'])->name('scrapes.artifact');
