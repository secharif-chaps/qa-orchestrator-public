<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

/**
 * Builds a complete {@see Fingerprint} bundle from raw document text by
 * running the dedup pipeline algorithms in canonical order
 * (ADR-2026-006):
 *
 *   normalize → shingle → SHA256 + SimHash + MinHash + LSH bands
 *
 * The four output layers are computed off the same shingle set so the
 * resulting bundle is internally consistent — two callers passing the
 * same input always produce a bit-identical {@see Fingerprint}.
 */
readonly class DocumentFingerprintComputer
{
    public function __construct(
        private TextNormalizer $textNormalizer,
        private ShingleExtractor $shingleExtractor,
        private SimHashGenerator $simHashGenerator,
        private MinHashGenerator $minHashGenerator,
        private LshBandGenerator $lshBandGenerator,
    ) {
    }

    public function compute(string $text, string $title = ''): Fingerprint
    {
        $normalized = $this->textNormalizer->normalize($text);
        $shingles = $this->shingleExtractor->extract($normalized);
        $minHashSignature = $this->minHashGenerator->generate($shingles);

        // Title shingles power stage 4 (title fallback) — the same
        // shingle scheme as the body, applied to the normalised title.
        // For typical 5-15 word titles this yields a handful of
        // shingles, indexed as a keyword array for cheap terms-query
        // candidate retrieval; empty title → empty list, stage 4 skips.
        $titleShingles = '' !== $title
            ? $this->shingleExtractor->extract($this->textNormalizer->normalize($title))
            : [];

        return new Fingerprint(
            // SHA256 over the normalised text — the byte-stable form of the
            // document, not the raw input. A trailing newline or HTML
            // wrapping shouldn't move the contentHash.
            contentHash: hash('sha256', $normalized),
            simHash: $this->simHashGenerator->generate($shingles),
            minHashSignature: $minHashSignature,
            lshBands: $this->lshBandGenerator->generate($minHashSignature),
            titleShingles: $titleShingles,
        );
    }
}
