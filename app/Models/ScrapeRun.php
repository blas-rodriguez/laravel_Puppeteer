<?php

namespace App\Models;

use Database\Factories\ScrapeRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapeRun extends Model
{
    /** @use HasFactory<ScrapeRunFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'url', 'item_selector', 'next_selector', 'fields', 'max_pages',
        'status', 'records', 'pages', 'duration_ms', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array', 'records' => 'array', 'pages' => 'array',
            'started_at' => 'datetime', 'finished_at' => 'datetime',
            'max_pages' => 'integer', 'duration_ms' => 'integer',
        ];
    }

    public function recipe(): array
    {
        return $this->only(['url', 'item_selector', 'next_selector', 'fields', 'max_pages']);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued' => 'En cola', 'running' => 'En ejecución',
            'completed' => 'Completado', 'failed' => 'Fallido', default => $this->status,
        };
    }
}
