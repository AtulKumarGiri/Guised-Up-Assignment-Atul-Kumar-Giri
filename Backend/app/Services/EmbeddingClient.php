<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.embedding.url', 'http://localhost:8000');
    }

    /**
     * Returns a 384-dim float array for the given text.
     * Falls back to a deterministic hash-based pseudo-embedding if the
     * Python service is unreachable — keeps the app runnable without the
     * ML dependency installed. This swap point is documented in the TSD.
     */
    public function embed(string $text): array
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/embed", ['text' => $text]);

            if ($response->successful()) {
                return $response->json('vector');
            }
        } catch (\Throwable $e) {
            Log::warning('Embedding service unreachable, falling back to hash embedding', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->hashEmbedding($text);
    }

    /**
     * Deterministic fallback: hashes the text into a fixed-size vector.
     * NOT semantically meaningful — only used when the real model is unavailable.
     */
    protected function hashEmbedding(string $text, int $dims = 384): array
    {
        $vector = [];
        $hash = hash('sha256', $text);

        for ($i = 0; $i < $dims; $i++) {
            $byte = hexdec(substr($hash, ($i % 32) * 2, 2));
            $vector[] = (($byte / 255) * 2) - 1; // normalize to [-1, 1]
        }

        return $vector;
    }
}
