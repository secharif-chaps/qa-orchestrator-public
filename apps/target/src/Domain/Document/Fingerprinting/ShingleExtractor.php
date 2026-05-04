<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

/**
 * Hybrid n-gram extractor:
 *   - space-separated scripts (Latin, Cyrillic, Arabic, Devanagari…)
 *     → 3-word shingles
 *   - CJK scripts (Chinese, Japanese, Korean) → 4-character n-grams
 *
 * The hybrid choice is delegated to {@see ScriptDetector}.
 *
 * Output shingles are returned **unhashed** — hashing happens in the
 * downstream `SimHashGenerator` / `MinHashGenerator` so each consumer can
 * pick the hash function best suited to its bit budget.
 *
 * @see ADR-2026-006 — Hybrid Shingle Strategy / Algorithm Implementation.
 */
readonly class ShingleExtractor
{
    public const int WORD_SHINGLE_SIZE = 3;
    public const int CHAR_NGRAM_SIZE = 4;

    public function __construct(
        private ScriptDetector $scriptDetector,
    ) {
    }

    /**
     * Extract a deduplicated list of shingles from already-normalised text
     * (see {@see TextNormalizer}).
     *
     * @return list<string>
     */
    public function extract(string $normalizedText): array
    {
        if ($this->scriptDetector->isCjkDominant($normalizedText)) {
            return $this->extractCharNgrams($normalizedText);
        }

        return $this->extractWordShingles($normalizedText);
    }

    /**
     * Sliding 4-char window over CJK text.
     *
     * `mb_str_split()` materialises the codepoint array once (O(N)) so that
     * each window costs O(1) — naive `mb_substr()` in a loop is O(N) per
     * call and would make the whole extraction O(N²) on long CJK texts.
     *
     * @return list<string>
     */
    private function extractCharNgrams(string $text): array
    {
        $text = (string) preg_replace('/\s+/u', '', $text);
        $chars = mb_str_split($text, 1, 'UTF-8');
        $length = \count($chars);

        if ($length < self::CHAR_NGRAM_SIZE) {
            return '' !== $text ? [$text] : [];
        }

        $ngrams = [];
        $limit = $length - self::CHAR_NGRAM_SIZE + 1;

        for ($i = 0; $i < $limit; ++$i) {
            $ngram = implode('', \array_slice($chars, $i, self::CHAR_NGRAM_SIZE));
            $ngrams[$ngram] = true;
        }

        return array_keys($ngrams);
    }

    /**
     * Sliding 3-word window over space-separated text.
     *
     * @return list<string>
     */
    private function extractWordShingles(string $text): array
    {
        // Defence against `TextNormalizer` emitting (or being bypassed by)
        // a whitespace-only string: `explode(' ', '   ')` would yield empty
        // tokens that turn into ghost shingles like `'  '`.
        $text = trim($text);
        if ('' === $text) {
            return [];
        }

        $words = explode(' ', $text);
        $wordCount = \count($words);

        if ($wordCount < self::WORD_SHINGLE_SIZE) {
            return [$text];
        }

        $shingles = [];
        $limit = $wordCount - self::WORD_SHINGLE_SIZE + 1;

        for ($i = 0; $i < $limit; ++$i) {
            $shingle = implode(' ', \array_slice($words, $i, self::WORD_SHINGLE_SIZE));
            $shingles[$shingle] = true;
        }

        return array_keys($shingles);
    }
}
