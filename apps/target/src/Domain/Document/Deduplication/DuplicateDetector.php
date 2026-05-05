<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Fingerprinting\Fingerprint;

/**
 * Pure-domain orchestrator for the four-stage dedup pipeline
 * (ADR-2026-006).
 *
 * Stages run cheapest-first and short-circuit on the first match. The
 * service is stateless and only depends on domain ports, so it sits in
 * Domain alongside the algorithms it composes — Infrastructure wiring
 * (gateway implementations, OpenSearch) lives behind the port.
 */
class DuplicateDetector implements DuplicateDetectorInterface
{
    /**
     * Hamming threshold below which a SimHash candidate is reported as
     * NEAR_EXACT. ADR-2026-006: ≤ 1 → reject, 2-3 → flag, > 3 → fall
     * through to Jaccard. The detector itself is reject/flag-agnostic;
     * the caller decides what to do with the verdict.
     */
    private const int SIMHASH_HAMMING_NEAR_EXACT_THRESHOLD = 3;

    /**
     * Estimated-Jaccard threshold for stage 3. Below this, candidates
     * sharing one or more LSH bands are dismissed as coincidental.
     *
     * Tuned to **0.70** based on the legacy AMI golden corpus: the
     * 0.85 threshold (industry-standard for MinHash-only dedup) was
     * dropping a measurable cluster of LLM-confirmed near-duplicates
     * in the 0.5–0.85 band — typically the same syndicated press
     * release re-reported by 2-3 outlets with light edits. 0.70 is
     * still well above random collision (at 128 hashes the standard
     * error is ~1/√128 ≈ 8.8 %), while reaching the wire-syndication
     * regime that dominates real-world duplicates.
     */
    private const float JACCARD_NEAR_DUPLICATE_THRESHOLD = 0.70;

    /**
     * Stage 4 — title fallback. Set-based Jaccard on the title shingles
     * of the candidate vs each title-shingle-overlap candidate from the
     * gateway.
     *
     * Tuned to **0.85** based on the legacy AMI golden corpus: every
     * true-positive stage-4 catch had `title_jaccard = 1.0` (verbatim
     * news headlines reposted across outlets). The only stage-4 false
     * positives sat at `title_jaccard ∈ [0.83, 1.0]` — generic "section
     * banner" titles re-used across distinct catalog pages of the same
     * site (e.g. "TRANSPARENCY PRODUCTS BY PPG FOR THE AEROSPACE
     * INDUSTRY"). Setting the threshold to 0.85 eliminates the lone
     * 0.83 FP without sacrificing any TP. The remaining
     * `title_jaccard = 1.0` FPs (5 PPG catalog pages on the corpus) are
     * not separable by threshold alone and require a future title-
     * specificity heuristic (e.g. "skip stage 4 if this title appears
     * on N+ docs in the index already").
     */
    private const float TITLE_FALLBACK_JACCARD_THRESHOLD = 0.85;

    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly FingerprintGatewayInterface $fingerprintGateway,
        private readonly FingerprintSimilarity $similarity,
    ) {
    }

    public function detect(
        ?Fingerprint $fingerprint = null,
        ?string $canonicalUrl = null,
        ?string $excludeDocumentId = null,
    ): DuplicateResult {
        $stage0 = $this->matchByCanonicalUrl($canonicalUrl, $excludeDocumentId);
        if (null !== $stage0) {
            return $stage0;
        }

        // Without a fingerprint we can only run stage 0 — there is
        // nothing to feed stages 1-4. Used for canonical-URL-only probes
        // on documents that carry a canonical tag but no body.
        if (null === $fingerprint) {
            return DuplicateResult::unique();
        }

        $stage1 = $this->matchByContentHash($fingerprint, $excludeDocumentId);
        if (null !== $stage1) {
            return $stage1;
        }

        $stage2 = $this->matchBySimHashExact($fingerprint, $excludeDocumentId);
        if (null !== $stage2) {
            return $stage2;
        }

        $stage3 = $this->matchByLshCandidates($fingerprint, $excludeDocumentId);
        if (null !== $stage3) {
            return $stage3;
        }

        $stage4 = $this->matchByTitleShingles($fingerprint, $excludeDocumentId);
        if (null !== $stage4) {
            return $stage4;
        }

        return DuplicateResult::unique();
    }

    private function matchByCanonicalUrl(?string $canonicalUrl, ?string $excludeDocumentId): ?DuplicateResult
    {
        if (null === $canonicalUrl || '' === $canonicalUrl) {
            return null;
        }

        return $this->exactMatch(
            $this->documentGateway->findByCanonicalUrl($canonicalUrl, $excludeDocumentId),
            DuplicateOutcome::DUPLICATE,
            DuplicateMatchStage::CANONICAL_URL,
        );
    }

    private function matchByContentHash(Fingerprint $fingerprint, ?string $excludeDocumentId): ?DuplicateResult
    {
        return $this->exactMatch(
            $this->fingerprintGateway->findByContentHash($fingerprint->contentHash, $excludeDocumentId),
            DuplicateOutcome::DUPLICATE,
            DuplicateMatchStage::CONTENT_HASH,
        );
    }

    private function matchBySimHashExact(Fingerprint $fingerprint, ?string $excludeDocumentId): ?DuplicateResult
    {
        // SimHash collisions on real text are rare; an exact match means
        // the two normalised shingle sets produced bit-identical hashes
        // (Hamming = 0), i.e. ~99 % textual similarity by SimHash's
        // contract. Worth a short-circuit before the costly LSH stage.
        return $this->exactMatch(
            $this->fingerprintGateway->findBySimHash($fingerprint->simHash, $excludeDocumentId)[0] ?? null,
            DuplicateOutcome::NEAR_EXACT,
            DuplicateMatchStage::SIM_HASH,
        );
    }

    /**
     * Build a similarity-1.0 verdict for a stage that proved its match
     * by an exact gateway lookup (canonical URL / SHA256 / SimHash).
     */
    private function exactMatch(
        ?Document $match,
        DuplicateOutcome $outcome,
        DuplicateMatchStage $stage,
    ): ?DuplicateResult {
        if (null === $match) {
            return null;
        }

        return DuplicateResult::match(
            outcome: $outcome,
            stage: $stage,
            originalDocumentId: $match->getId(),
            similarity: 1.0,
        );
    }

    private function matchByLshCandidates(Fingerprint $fingerprint, ?string $excludeDocumentId): ?DuplicateResult
    {
        $candidates = $this->fingerprintGateway->findByLshBands($fingerprint->lshBands, $excludeDocumentId);
        if ([] === $candidates) {
            return null;
        }

        $best = null;
        foreach ($candidates as $candidate) {
            $candidateFingerprint = $candidate->getFingerprint();
            if (null === $candidateFingerprint) {
                // Document indexed before fingerprinting shipped — can't
                // verify similarity. Skip rather than guess.
                continue;
            }

            $verdict = $this->verifyCandidate($fingerprint, $candidateFingerprint, $candidate);
            if (null === $verdict) {
                continue;
            }

            if (null === $best || $verdict->similarity > $best->similarity) {
                $best = $verdict;
            }
        }

        return $best;
    }

    /**
     * Compare a candidate against the input fingerprint. Returns the
     * matching verdict if Hamming or Jaccard clear their thresholds —
     * the caller keeps the highest-similarity verdict across candidates.
     */
    private function verifyCandidate(
        Fingerprint $fingerprint,
        Fingerprint $candidateFingerprint,
        Document $candidate,
    ): ?DuplicateResult {
        $hamming = $this->similarity->hamming($fingerprint, $candidateFingerprint);
        if ($hamming <= self::SIMHASH_HAMMING_NEAR_EXACT_THRESHOLD) {
            return DuplicateResult::match(
                outcome: DuplicateOutcome::NEAR_EXACT,
                stage: DuplicateMatchStage::MIN_HASH_LSH,
                originalDocumentId: $candidate->getId(),
                similarity: $this->similarity->hammingToSimilarity($hamming),
            );
        }

        $jaccard = $this->similarity->jaccard($fingerprint, $candidateFingerprint);
        if ($jaccard >= self::JACCARD_NEAR_DUPLICATE_THRESHOLD) {
            return DuplicateResult::match(
                outcome: DuplicateOutcome::NEAR_DUPLICATE,
                stage: DuplicateMatchStage::MIN_HASH_LSH,
                originalDocumentId: $candidate->getId(),
                similarity: $jaccard,
            );
        }

        return null;
    }

    /**
     * Stage 4 — runs only when stages 0-3 have rejected the candidate as
     * UNIQUE. Targets news-syndication / paraphrase pairs whose body
     * shingles barely overlap (Jaccard < 0.2 typical) but whose titles
     * are intact across outlets ("Vestas receives an order for 65 MW…"
     * lands verbatim on multiple sites).
     *
     * Skipped when the candidate has no title shingles (empty title or
     * legacy fingerprint pre-stage-4) — there is nothing to probe with.
     */
    private function matchByTitleShingles(Fingerprint $fingerprint, ?string $excludeDocumentId): ?DuplicateResult
    {
        if ([] === $fingerprint->titleShingles) {
            return null;
        }

        $candidates = $this->fingerprintGateway->findByTitleShingles(
            $fingerprint->titleShingles,
            $excludeDocumentId,
        );
        if ([] === $candidates) {
            return null;
        }

        $best = null;
        foreach ($candidates as $candidate) {
            $candidateFingerprint = $candidate->getFingerprint();
            if (null === $candidateFingerprint) {
                continue;
            }

            $jaccard = $this->similarity->titleShingleJaccard($fingerprint, $candidateFingerprint);
            if ($jaccard < self::TITLE_FALLBACK_JACCARD_THRESHOLD) {
                continue;
            }

            if (null === $best || $jaccard > $best->similarity) {
                $best = DuplicateResult::match(
                    outcome: DuplicateOutcome::NEAR_DUPLICATE,
                    stage: DuplicateMatchStage::TITLE_FALLBACK,
                    originalDocumentId: $candidate->getId(),
                    similarity: $jaccard,
                );
            }
        }

        return $best;
    }
}
