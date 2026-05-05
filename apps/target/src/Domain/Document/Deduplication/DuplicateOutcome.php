<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

/**
 * Outcome of a single dedup-pipeline run on a freshly-collected document
 * (ADR-2026-006).
 *
 * - `DUPLICATE`      — exact match found via stage 0 (canonical URL) or
 *                      stage 1 (SHA256 content hash). The candidate is
 *                      identical to an already-indexed document and is
 *                      rejected without persistence.
 * - `NEAR_EXACT`     — stage 2 SimHash Hamming ≤ 3. ≈ 95-98 % similar.
 *                      Hamming ≤ 1 is automatically rejected; 2-3 is
 *                      flagged for review.
 * - `NEAR_DUPLICATE` — stage 3 MinHash + LSH detected a candidate with
 *                      Jaccard ≥ 0.85. Flagged for review.
 * - `UNIQUE`         — no match across the four stages. The document is
 *                      indexed normally.
 */
enum DuplicateOutcome: string
{
    case DUPLICATE = 'duplicate';
    case NEAR_EXACT = 'near_exact';
    case NEAR_DUPLICATE = 'near_duplicate';
    case UNIQUE = 'unique';
}
