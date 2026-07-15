<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\EmbeddingClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __construct(protected EmbeddingClient $embeddingClient)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:500',
        ]);

        $queryVector = $this->embeddingClient->embed($validated['q']);
        $vectorLiteral = Post::vectorLiteral($queryVector);

        // pgvector cosine distance operator <=> ; lower distance = more similar
        $results = DB::select("
            SELECT
                p.id, p.text, p.image_url, p.user_id AS author_id, p.created_at,
                1 - (p.embedding <=> ?::vector) AS similarity
            FROM posts p
            ORDER BY p.embedding <=> ?::vector
            LIMIT 10
        ", [$vectorLiteral, $vectorLiteral]);

        return response()->json(['data' => $results]);
    }
}
