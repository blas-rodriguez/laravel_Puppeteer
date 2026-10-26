<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('url');
            $table->string('item_selector', 500);
            $table->string('next_selector', 500)->nullable();
            $table->json('fields');
            $table->unsignedTinyInteger('max_pages')->default(1);
            $table->string('status', 20)->default('queued')->index();
            $table->json('records')->nullable();
            $table->json('pages')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
