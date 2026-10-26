<?php

namespace Database\Factories;

use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeRun>
 */
class ScrapeRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Citas de ejemplo',
            'url' => 'https://quotes.toscrape.com/',
            'item_selector' => '.quote',
            'next_selector' => '.next a',
            'fields' => [['name' => 'cita', 'selector' => '.text'], ['name' => 'autor', 'selector' => '.author']],
            'max_pages' => 1,
            'status' => 'queued',
        ];
    }
}
