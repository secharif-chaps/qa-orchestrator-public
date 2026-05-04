<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

/**
 * Detects whether a string is dominated by CJK (Chinese, Japanese, Korean)
 * characters. Used by `ShingleExtractor` to switch between word-shingles
 * (space-separated scripts) and character n-grams (no word boundaries).
 *
 * @see ADR-2026-006 — Multilingual Support / Hybrid Shingle Strategy.
 */
readonly class ScriptDetector
{
    /**
     * Default ratio of CJK characters above which a string is considered
     * CJK-dominant. Chosen to keep mostly-Latin texts with a few CJK names
     * on the word-shingle path while routing real CJK content (~all chars
     * are CJK) to the character n-gram path.
     */
    public const float DEFAULT_CJK_THRESHOLD = 0.30;

    /**
     * Single source of truth for CJK character detection across the
     * codebase (this detector + `Document::getWordCount()`).
     *
     * `\p{…}` Unicode property classes are preferred over hex ranges:
     * they cover every published Han extension (A through G,
     * Compatibility, Radicals…) plus all Hangul Jamo blocks, so a rare
     * ideograph or archaic Hangul codepoint is recognised without
     * having to maintain a hand-curated range list.
     */
    public const string CJK_PATTERN = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u';

    public function isCjkDominant(string $text, float $threshold = self::DEFAULT_CJK_THRESHOLD): bool
    {
        $totalChars = mb_strlen($text, 'UTF-8');
        if (0 === $totalChars) {
            return false;
        }

        $cjkChars = preg_match_all(self::CJK_PATTERN, $text);

        return ($cjkChars / $totalChars) >= $threshold;
    }
}
