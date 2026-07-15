<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeedRankingService
{
    protected const W_AUTH = 0.25;
    protected const W_REL  = 0.30;
    protected const W_SEM  = 0.30;
    protected const W_TIME = 0.15;
    protected const LAMBDA = 0.0144; // ~48h half-life: ln(2)/48

    public function __construct(protected EmbeddingClient $embeddingClient)
    {
    }

    /**
     * Returns a paginated, ranked feed for the given user.
     */
    public function feedFor(User $user, int $perPage = 20, int $page = 1)
    {
        $interestVector = $this->interestVector($user);
        $vectorLiteral = Post::vectorLiteral($interestVector);
        $maxInteraction = $this->maxAuthorInteractionCount($user);
        $maxInteraction = max(1, $maxInteraction); // avoid div by zero

        $offset = ($page - 1) * $perPage;

        // Single SQL query computes all four sub-scores and the composite score.
        // relationship_depth: interactions the viewer has logged against the post's author, last 30 days
        // semantic_similarity: 1 - cosine_distance (pgvector's <=> returns cosine distance)
        // time_decay: exponential decay based on post age in hours
        $rows = DB::select("
            SELECT
                p.id,
                p.text,
                p.image_url,
                p.authenticity_score,
                p.created_at,
                p.user_id AS author_id,
                COALESCE(rel.interaction_count, 0) AS relationship_raw,
                GREATEST(0, 1 - (p.embedding <=> ?::vector)) AS semantic_similarity,
                EXP(-(CAST(? AS DOUBLE PRECISION))
    * EXTRACT(EPOCH FROM (NOW() - p.created_at))
    / 3600.0) AS time_decay
            FROM posts p
            LEFT JOIN (
                SELECT po.user_id AS author_id, COUNT(*) AS interaction_count
                FROM interactions i
                JOIN posts po ON po.id = i.post_id
                WHERE i.user_id = ?
                  AND i.created_at >= NOW() - INTERVAL '30 days'
                GROUP BY po.user_id
            ) rel ON rel.author_id = p.user_id
            ORDER BY p.created_at DESC
            LIMIT 500
        ", [$vectorLiteral, self::LAMBDA, $user->id]);

        $scored = collect($rows)->map(function ($row) use ($maxInteraction) {
            $relationshipDepth = min(1, $row->relationship_raw / $maxInteraction);

            $row->score =
                (self::W_AUTH * (float) $row->authenticity_score) +
                (self::W_REL  * $relationshipDepth) +
                (self::W_SEM  * (float) $row->semantic_similarity) +
                (self::W_TIME * (float) $row->time_decay);

            return $row;
        })->sortByDesc('score')->values();

        $total = $scored->count();
        $page = $scored->slice($offset, $perPage)->values();

        return [
            'data' => $page,
            'meta' => [
                'current_page' => (int) request('page', 1),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * Rolling interest vector: average embedding of posts the user has
     * interacted with (reply/reaction weighted higher than view) in last 30 days.
     */
    protected function interestVector(User $user): array
    {
        $rows = DB::select("
            SELECT p.embedding, i.type
            FROM interactions i
            JOIN posts p ON p.id = i.post_id
            WHERE i.user_id = ?
              AND i.created_at >= NOW() - INTERVAL '30 days'
            ORDER BY i.created_at DESC
            LIMIT 200
        ", [$user->id]);

        if (empty($rows)) {
            // Cold start: no signal yet, return zero vector (neutral similarity)
            return array_fill(0, 384, 0);
        }

        $sum = array_fill(0, 384, 0.0);
        $totalWeight = 0.0;

        foreach ($rows as $row) {
            $weight = match ($row->type) {
                'reaction' => 2.0,
                'reply' => 3.0,
                default => 1.0, // view
            };

            $vec = $this->parseVector($row->embedding);
            foreach ($vec as $i => $v) {
                $sum[$i] += $v * $weight;
            }
            $totalWeight += $weight;
        }

        return array_map(fn ($v) => $totalWeight > 0 ? $v / $totalWeight : 0, $sum);
    }

    protected function maxAuthorInteractionCount(User $user): int
    {
        $result = DB::selectOne("
            SELECT MAX(cnt) AS max_cnt FROM (
                SELECT po.user_id, COUNT(*) AS cnt
                FROM interactions i
                JOIN posts po ON po.id = i.post_id
                WHERE i.user_id = ?
                  AND i.created_at >= NOW() - INTERVAL '30 days'
                GROUP BY po.user_id
            ) sub
        ", [$user->id]);

        return (int) ($result->max_cnt ?? 0);
    }

    protected function parseVector(string $pgVectorString): array
    {
        return array_map('floatval', explode(',', trim($pgVectorString, '[]')));
    }
}
