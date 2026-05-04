<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

/**
 * Normalises arbitrary text into a deterministic form suitable for
 * fingerprinting (shingles, SimHash, MinHash). The output is **only** intended
 * for hashing — it is never rendered back to a user, so we don't need
 * XSS-grade sanitisation, but we want stability and idempotency.
 *
 * @see ADR-2026-006 (Document Deduplication Strategy) — `TextNormalizer`.
 */
readonly class TextNormalizer
{
    /**
     * `<script>` and `<style>` carry text payloads that `strip_tags()`
     * leaves intact when it removes the surrounding tag — so JS and CSS
     * leak into the fingerprint. Drop those blocks (and their content)
     * before stripping the rest of the markup.
     *
     * Same treatment for `<noscript>` and HTML comments so a comment-only
     * change doesn't move the fingerprint.
     */
    private const string DROP_BLOCK_PATTERN =
        '#<(script|style|noscript)\b[^>]*>.*?</\1>|<!--.*?-->#is';

    /**
     * Codepoints that are visually invisible but semantically distinct.
     * Two strings that look identical but differ on these would produce
     * different fingerprints — so we strip them outright. Removing rather
     * than replacing with space keeps "helloworld" (with U+200B) from
     * accidentally splitting into two words.
     *
     *   - U+0000 NUL
     *   - U+200B ZERO WIDTH SPACE
     *   - U+200C ZERO WIDTH NON-JOINER
     *   - U+200D ZERO WIDTH JOINER
     *   - U+FEFF ZERO WIDTH NO-BREAK SPACE / BOM
     *   - U+2060 WORD JOINER
     *   - U+202A–E (LRE/RLE/PDF/LRO/RLO) — bidi formatting
     *   - U+2066–9 (LRI/RLI/FSI/PDI) — bidi isolates (Trojan Source)
     */
    private const string INVISIBLE_PATTERN =
        '/[\x{0000}\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /**
     * Pipeline:
     *   1. drop `<script>` / `<style>` / `<noscript>` blocks **with content**
     *   2. drop remaining HTML tags (kept content stays)
     *   3. decode HTML entities (after strip — entities can't reintroduce tags)
     *   4. NFC normalisation — collapse e.g. `"e\u{0301}"` and `"é"` to the
     *      same byte sequence so visually identical strings hash identically
     *   5. drop invisible / bidi-control / NUL codepoints
     *   6. Unicode-aware lowercase
     *   7. drop everything that's not a letter / number / whitespace
     *   8. collapse whitespace runs into single spaces, trim
     *
     * Cost: O(n) on the input length. Caller is responsible for bounding
     * the input size — this method does not truncate.
     */
    public function normalize(string $text): string
    {
        $text = (string) preg_replace(self::DROP_BLOCK_PATTERN, ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        $text = (string) preg_replace(self::INVISIBLE_PATTERN, '', $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return (string) preg_replace('/\s+/', ' ', trim($text));
    }
}
