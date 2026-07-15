<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\EmbeddingClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function __construct(protected EmbeddingClient $embeddingClient)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:5000',
            'image_url' => 'nullable|url|max:2048',
            'has_filter' => 'nullable|boolean',
        ]);

        $embedding = $this->embeddingClient->embed($validated['text']);
        $authenticityScore = $this->computeAuthenticityScore($validated['text'], $validated['has_filter'] ?? false);

        $post = new Post([
            'user_id' => $request->user()->id,
            'text' => $validated['text'],
            'image_url' => $validated['image_url'] ?? null,
            'has_filter' => $validated['has_filter'] ?? false,
            'authenticity_score' => $authenticityScore,
        ]);
        $post->save();

        // pgvector column set via raw SQL since Eloquent has no native vector cast
        DB::statement('UPDATE posts SET embedding = ?::vector WHERE id = ?', [
            Post::vectorLiteral($embedding),
            $post->id,
        ]);

        return response()->json($post->fresh(), 201);
    }

    /**
     * Simple heuristic: longer, non-filtered posts score higher on "authenticity".
     * Documented as an assumption in the TSD — real system would need image analysis.
     */
    protected function computeAuthenticityScore(string $text, bool $hasFilter): float
    {
        $lengthScore = min(1, str_word_count($text) / 40); // saturates around 40 words
        $filterPenalty = $hasFilter ? 0.3 : 0;

        return round(max(0, min(1, $lengthScore + 0.5 - $filterPenalty)), 3);
    }
}
