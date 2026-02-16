<?php

namespace App\Console\Commands;

use App\Services\KnowledgeService;
use Illuminate\Console\Command;

class ScrapeKnowledge extends Command
{
    protected $signature = 'knowledge:scrape
        {--source= : Scrape only a specific URL}
        {--fresh : Delete all existing chunks and re-scrape everything}';

    protected $description = 'Scrape Danish family law sources, chunk text, and create embeddings for RAG';

    public function handle(KnowledgeService $service): int
    {
        $this->info('🔍 Aura Knowledge Scraper');
        $this->info('========================');

        if ($this->option('fresh')) {
            $count = \App\Models\KnowledgeChunk::count();
            $this->warn("Sletter {$count} eksisterende chunks...");
            \App\Models\KnowledgeChunk::truncate();
        }

        $sources = KnowledgeService::getSources();
        $specificUrl = $this->option('source');

        if ($specificUrl) {
            $sources = array_filter($sources, fn($s) => $s['url'] === $specificUrl);
            if (empty($sources)) {
                $this->error("URL ikke fundet i kildelisten: {$specificUrl}");
                return 1;
            }
        }

        $this->info('Kilder: ' . count($sources));
        $this->newLine();

        $totalChunks = 0;

        foreach ($sources as $source) {
            $this->info("📄 {$source['title']}");

            $stored = $service->processSource($source, function ($msg) {
                $this->line("  {$msg}");
            });

            $totalChunks += $stored;
            $this->newLine();
        }

        $total = \App\Models\KnowledgeChunk::count();
        $withEmbeddings = \App\Models\KnowledgeChunk::whereNotNull('embedding')->count();

        $this->newLine();
        $this->info('========================');
        $this->info("✅ Færdig! {$totalChunks} nye chunks oprettet");
        $this->info("📊 Total: {$total} chunks ({$withEmbeddings} med embeddings)");

        return 0;
    }
}
