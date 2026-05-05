<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Trace of a collect attempt that the dedup pipeline matched against an
 * already-indexed document (ADR-2026-006, axe A).
 *
 * Stored on the **original (winning)** document — i.e. the doc the new
 * candidate was matched against. Captures the operational context of the
 * tentative collect (URL, watch_file, collect task, source, provider,
 * timestamp) plus the dedup result (`outcome`, `matchStage`, `similarity`).
 *
 * Used downstream for:
 * - "vu sur AFP, Le Monde, Reuters" UX (republication signal),
 * - article-popularity / collect statistics per source / per watch_file,
 * - audit on PO-reported false positives ("which doc did we reject?").
 */
readonly class DuplicateAttempt
{
    public function __construct(
        #[ApiProperty(
            description: 'Canonical URL of the duplicate candidate, normalised the same way the master URL is normalised in the canonical-URL stage.',
            example: 'https://www.lemonde.fr/economie/article/2026/02/14/exemple_6234567_3234.html',
            openapiContext: [
                'type' => 'string',
                'format' => 'uri',
            ],
        )]
        #[Groups(['document:read', 'document:save'])]
        public string $url,
        #[Groups(['document:save'])]
        public string $watchFileId,
        #[Groups(['document:save'])]
        public string $collectTaskId,
        #[Groups(['document:save'])]
        public ?string $sourceId,
        #[Groups(['document:save'])]
        public string $provider,
        #[ApiProperty(
            description: 'Server-side wall-clock timestamp at which the candidate was fetched (ISO-8601, UTC).',
            example: '2026-04-30T14:23:51+00:00',
            openapiContext: [
                'type' => 'string',
                'format' => 'date-time',
            ],
        )]
        #[Groups(['document:read', 'document:save'])]
        public \DateTimeImmutable $collectedAt,
        #[ApiProperty(
            description: <<<'EOT'
                Dedup-pipeline verdict on the candidate.

                - `duplicate`       — exact match (canonical URL or SHA-256 content hash).
                - `near_exact`      — SimHash Hamming ≤ 3 (≈ 95-98 % similar).
                - `near_duplicate`  — MinHash + LSH detected Jaccard ≥ 0.85.

                `unique` is **never** stored on a `DuplicateAttempt` — there is no
                matched original to attach it to.
                EOT
            ,
            example: 'duplicate',
            openapiContext: [
                'type' => 'string',
                'enum' => ['duplicate', 'near_exact', 'near_duplicate'],
            ],
        )]
        #[Groups(['document:read', 'document:save'])]
        public DuplicateOutcome $outcome,
        #[ApiProperty(
            description: <<<'EOT'
                Stage of the dedup pipeline that produced the match. Stages are
                ordered from cheapest to most expensive and the pipeline
                short-circuits on the first hit:

                - `canonical_url` — keyword lookup on the normalised canonical URL.
                - `content_hash`  — SHA-256 keyword lookup on the normalised body.
                - `sim_hash`      — 64-bit SimHash equality + Hamming-distance scan.
                - `min_hash_lsh`  — LSH-band candidate fan-out + Jaccard verify.
                EOT
            ,
            example: 'content_hash',
            openapiContext: [
                'type' => 'string',
                'enum' => ['canonical_url', 'content_hash', 'sim_hash', 'min_hash_lsh'],
            ],
        )]
        #[Groups(['document:read', 'document:save'])]
        public DuplicateMatchStage $matchStage,
        #[ApiProperty(
            description: <<<'EOT'
                Similarity score the matching stage produced for this pair.

                - `canonical_url` / `content_hash` — `null` (exact match, no score).
                - `sim_hash`      — 64-bit Hamming distance encoded as `1 - hamming/64`
                  (so higher = more similar, in `[0, 1]`).
                - `min_hash_lsh`  — Jaccard estimate over the 128-bit MinHash
                  signatures (in `[0, 1]`).
                EOT
            ,
            example: 0.92,
            openapiContext: [
                'type' => 'number',
                'format' => 'float',
                'minimum' => 0,
                'maximum' => 1,
                'nullable' => true,
            ],
        )]
        #[Groups(['document:read', 'document:save'])]
        public ?float $similarity = null,
    ) {
        if (DuplicateOutcome::UNIQUE === $outcome) {
            throw new \InvalidArgumentException(
                'A DuplicateAttempt cannot record the UNIQUE outcome — there is no matched original to attach it to.',
            );
        }
    }
}
