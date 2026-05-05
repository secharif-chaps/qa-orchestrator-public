<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

/**
 * Identifies which stage of the dedup pipeline produced a match
 * (ADR-2026-006). Ordered from cheapest to most expensive — the pipeline
 * short-circuits as soon as one of them matches.
 *
 * - `CANONICAL_URL`  — keyword lookup on the normalised canonical URL.
 * - `CONTENT_HASH`   — SHA256 keyword lookup on the normalised text body.
 * - `SIM_HASH`       — 64-bit SimHash equality + Hamming-distance scan.
 * - `MIN_HASH_LSH`   — LSH-band candidate fan-out + Jaccard verification.
 * - `TITLE_FALLBACK` — title-shingle Jaccard, runs only when stages 0-3
 *                     return UNIQUE. Catches news syndication / paraphrase
 *                     pairs whose body shingles barely overlap but whose
 *                     titles are intact.
 */
enum DuplicateMatchStage: string
{
    case CANONICAL_URL = 'canonical_url';
    case CONTENT_HASH = 'content_hash';
    case SIM_HASH = 'sim_hash';
    case MIN_HASH_LSH = 'min_hash_lsh';
    case TITLE_FALLBACK = 'title_fallback';
}
