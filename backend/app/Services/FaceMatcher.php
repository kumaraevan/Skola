<?php

namespace App\Services;

use App\Models\FaceEmbedding;

/**
 * 1:N face matching. The tablet sends an embedding (produced on-device); this finds
 * the closest enrolled embedding by cosine similarity and applies a threshold.
 *
 * ponytail: brute-force cosine in PHP. Fine at school scale (<~5k faces). In production
 * on Postgres, move this to a pgvector `vector` column + ANN index and match in SQL.
 */
class FaceMatcher
{
    public function __construct(private float $threshold) {}

    /** Cosine similarity of two equal-length vectors; -1.0 if lengths differ or either is empty/zero. */
    public static function cosine(array $a, array $b): float
    {
        $n = count($a);
        if ($n === 0 || $n !== count($b)) {
            return -1.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $i => $x) {
            $y = $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return -1.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Best matching embedding for the query, or null if none clears the threshold.
     *
     * @param  iterable<FaceEmbedding>  $embeddings
     * @return array{embedding: FaceEmbedding, score: float}|null
     */
    public function best(array $query, iterable $embeddings): ?array
    {
        $bestScore = -1.0;
        $best = null;
        foreach ($embeddings as $embedding) {
            $score = self::cosine($query, $embedding->embedding ?? []);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $embedding;
            }
        }

        if ($best === null || $bestScore < $this->threshold) {
            return null;
        }

        return ['embedding' => $best, 'score' => $bestScore];
    }
}
