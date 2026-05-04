<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\TextNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextNormalizer::class)]
class TextNormalizerTest extends TestCase
{
    private TextNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TextNormalizer();
    }

    // ─── Empty / whitespace / trivial ────────────────────────────────────

    public function testEmptyInputReturnsEmptyString(): void
    {
        self::assertSame('', $this->normalizer->normalize(''));
    }

    public function testWhitespaceOnlyInputReturnsEmptyString(): void
    {
        self::assertSame('', $this->normalizer->normalize("   \t\n  "));
    }

    public function testCollapsesWhitespaceRuns(): void
    {
        self::assertSame('a b c', $this->normalizer->normalize("a    b\t\tc\n\n"));
    }

    public function testHtmlOnlyInputBecomesEmpty(): void
    {
        // No textual content survives — output must be the empty string,
        // not whitespace, so downstream `extract('')` short-circuits.
        self::assertSame('', $this->normalizer->normalize('<p></p><div></div><br/>'));
    }

    public function testScriptOnlyInputBecomesEmpty(): void
    {
        // The script body is dropped wholesale; nothing remains.
        self::assertSame('', $this->normalizer->normalize('<script>alert(1)</script>'));
    }

    public function testInvisibleOnlyInputBecomesEmpty(): void
    {
        self::assertSame('', $this->normalizer->normalize("\u{200B}\u{FEFF}\u{200D}"));
    }

    public function testPunctuationOnlyInputBecomesEmpty(): void
    {
        // Letters/digits filter strips everything except whitespace, then
        // trim collapses to an empty string.
        self::assertSame('', $this->normalizer->normalize('!!!???... — , .'));
    }

    public function testHtmlCommentOnlyInputBecomesEmpty(): void
    {
        self::assertSame('', $this->normalizer->normalize('<!-- only a comment -->'));
    }

    // ─── HTML stripping ──────────────────────────────────────────────────

    public function testStripsHtmlTags(): void
    {
        self::assertSame('hello world', $this->normalizer->normalize('<p>Hello <strong>world</strong></p>'));
    }

    public function testHandlesMalformedNestedTags(): void
    {
        $result = $this->normalizer->normalize('<scr<script>ipt>alert</scr</script>ipt>');

        self::assertStringNotContainsString('<', $result);
        self::assertStringNotContainsString('>', $result);
    }

    public function testStripsScriptContent(): void
    {
        // `strip_tags()` alone leaves script payloads behind, leaking JS
        // tokens into the fingerprint. The block-drop pass must remove
        // both tag and content.
        $result = $this->normalizer->normalize(
            '<p>Hello</p><script>var token = "secret_xyz_42";</script><p>World</p>',
        );

        self::assertStringNotContainsString('token', $result);
        self::assertStringNotContainsString('secret_xyz_42', $result);
        self::assertSame('hello world', $result);
    }

    public function testStripsStyleContent(): void
    {
        $result = $this->normalizer->normalize('<p>Hello</p><style>body { color: red; }</style><p>World</p>');

        self::assertStringNotContainsString('body', $result);
        self::assertStringNotContainsString('color', $result);
        self::assertSame('hello world', $result);
    }

    public function testStripsNoscriptContent(): void
    {
        $result = $this->normalizer->normalize('<p>Hello</p><noscript>JavaScript required</noscript><p>World</p>');

        self::assertStringNotContainsString('javascript', $result);
        self::assertSame('hello world', $result);
    }

    public function testStripsHtmlComments(): void
    {
        // A comment-only change must not move the fingerprint.
        $a = $this->normalizer->normalize('<p>Hello</p><!-- internal note 1 --><p>World</p>');
        $b = $this->normalizer->normalize('<p>Hello</p><!-- totally different note --><p>World</p>');

        self::assertSame($a, $b);
        self::assertStringNotContainsString('internal', $a);
        self::assertStringNotContainsString('different', $a);
    }

    public function testHandlesScriptWithAttributes(): void
    {
        $result = $this->normalizer->normalize(
            '<script type="application/json" data-id="42">{"secret":"x"}</script>visible',
        );

        self::assertSame('visible', $result);
    }

    public function testHandlesScriptCaseInsensitive(): void
    {
        $result = $this->normalizer->normalize('<SCRIPT>alert(1)</SCRIPT>visible');

        self::assertSame('visible', $result);
    }

    public function testHandlesMultilineScript(): void
    {
        $result = $this->normalizer->normalize("<script>\nvar x = 1;\nvar y = 2;\n</script>visible");

        self::assertSame('visible', $result);
    }

    // ─── HTML entities ───────────────────────────────────────────────────

    public function testDecodesHtmlEntitiesAfterStripping(): void
    {
        $result = $this->normalizer->normalize('Caf&eacute; &amp; th&eacute; &lt;script&gt;');

        self::assertSame('café thé script', $result);
    }

    public function testDecodesNumericEntities(): void
    {
        // Numeric entities can encode literally any codepoint, including
        // tag delimiters. The order (strip → decode) prevents injection.
        $result = $this->normalizer->normalize('&#60;b&#62;hi&#60;/b&#62;');

        self::assertStringNotContainsString('<', $result);
        self::assertSame('b hi b', $result);
    }

    public function testHandlesDoubleEncodedEntities(): void
    {
        // `&amp;lt;` decodes to `&lt;` (literal text), then the `<` and
        // `>` characters fall under the punctuation pass.
        $result = $this->normalizer->normalize('&amp;lt;script&amp;gt;');

        self::assertStringNotContainsString('<', $result);
        self::assertSame('lt script gt', $result);
    }

    // ─── Unicode handling ────────────────────────────────────────────────

    public function testLowercasesUnicode(): void
    {
        self::assertSame('éèà öäü ñ', $this->normalizer->normalize('ÉÈÀ ÖÄÜ Ñ'));
    }

    public function testRemovesPunctuationKeepsLettersAndDigits(): void
    {
        self::assertSame('hello world 2026 ai', $this->normalizer->normalize('Hello, world! (2026) — AI?'));
    }

    public function testKeepsCjkCharacters(): void
    {
        self::assertSame(
            '東京都は日本の首都です',
            $this->normalizer->normalize('東京都は日本の首都です。'),
        );
    }

    public function testNfcNormalisesCombiningSequences(): void
    {
        // Pre-composed `é` (U+00E9) and decomposed `e\u{0301}` look
        // identical but are different byte sequences. NFC must collapse
        // them so the fingerprint is stable across input encodings.
        $precomposed = $this->normalizer->normalize("caf\u{00E9}");
        $decomposed = $this->normalizer->normalize("cafe\u{0301}");

        self::assertSame($precomposed, $decomposed);
    }

    public function testNfcIsAppliedRecursivelyForKoreanJamo(): void
    {
        // Hangul: U+1100 (ᄀ) + U+1161 (ᅡ) + U+11A8 (ᆨ) compose into U+AC01 (각).
        $jamo = "\u{1100}\u{1161}\u{11A8}";
        $syllable = "\u{AC01}";

        self::assertSame($this->normalizer->normalize($syllable), $this->normalizer->normalize($jamo));
    }

    // ─── Invisible / control characters ──────────────────────────────────

    public function testStripsZeroWidthSpace(): void
    {
        // ZWSP between letters must not split a word — the codepoint is
        // dropped (not replaced with space) so the surrounding letters
        // collapse together.
        $result = $this->normalizer->normalize("hello\u{200B}world");

        self::assertSame('helloworld', $result);
    }

    public function testStripsZeroWidthJoinerAndNonJoiner(): void
    {
        $result = $this->normalizer->normalize("a\u{200C}b\u{200D}c");

        self::assertSame('abc', $result);
    }

    public function testStripsByteOrderMark(): void
    {
        $result = $this->normalizer->normalize("\u{FEFF}hello");

        self::assertSame('hello', $result);
    }

    public function testStripsBidirectionalControlChars(): void
    {
        // CVE-2021-42574 (Trojan Source): bidi controls let two visually
        // identical files have different content. Drop them so the
        // fingerprint reflects what the reader sees, not the byte stream.
        $clean = $this->normalizer->normalize('hello world');
        $withBidi = $this->normalizer->normalize("hello\u{202E} world\u{2069}");

        self::assertSame($clean, $withBidi);
    }

    public function testStripsNullBytes(): void
    {
        $result = $this->normalizer->normalize("hello\x00world");

        self::assertSame('helloworld', $result);
    }

    public function testStripsZeroWidthChardsDecodedFromEntities(): void
    {
        // ZWSP smuggled through a numeric entity must still be removed —
        // the invisible-strip pass runs **after** entity decoding.
        $result = $this->normalizer->normalize('hello&#x200B;world');

        self::assertSame('helloworld', $result);
    }

    // ─── Idempotence and stability ───────────────────────────────────────

    public function testIsIdempotent(): void
    {
        $input = '<p>Hello,    World!</p> &amp; more...';
        $once = $this->normalizer->normalize($input);
        $twice = $this->normalizer->normalize($once);

        self::assertSame($once, $twice);
    }

    public function testIsIdempotentOnComplexInput(): void
    {
        $input = "<script>x=1</script><p>Caf\u{00E9}\u{200B}!</p>\u{FEFF}";
        $once = $this->normalizer->normalize($input);
        $twice = $this->normalizer->normalize($once);
        $thrice = $this->normalizer->normalize($twice);

        self::assertSame($once, $twice);
        self::assertSame($twice, $thrice);
    }

    public function testIsDeterministic(): void
    {
        // Two calls on the same input must produce identical bytes —
        // hashing relies on this.
        $a = $this->normalizer->normalize('<b>Hello</b>, world!');
        $b = $this->normalizer->normalize('<b>Hello</b>, world!');

        self::assertSame($a, $b);
    }

    // ─── Performance / robustness ────────────────────────────────────────

    public function testHandlesLongInputLinearly(): void
    {
        $longInput = str_repeat('Hello world ', 10_000);

        $start = microtime(true);
        $result = $this->normalizer->normalize($longInput);
        $elapsed = microtime(true) - $start;

        self::assertNotEmpty($result);
        self::assertLessThan(2.0, $elapsed, 'TextNormalizer took too long on benign input');
    }

    public function testHandlesAdversarialScriptBlock(): void
    {
        // Long script content padded with closing-tag near-misses must not
        // trigger catastrophic backtracking on the lazy `.*?` matcher.
        $payload = str_repeat('var x = "</scrip"; ', 1_000);
        $input = '<p>visible</p><script>' . $payload . '</script>';

        $start = microtime(true);
        $result = $this->normalizer->normalize($input);
        $elapsed = microtime(true) - $start;

        self::assertSame('visible', $result);
        self::assertLessThan(2.0, $elapsed, 'Script-strip is exposed to ReDoS');
    }

    public function testHandlesInvalidUtf8WithoutCrash(): void
    {
        $broken = "valid \xC3\x28 text";

        $this->expectNotToPerformAssertions();
        $this->normalizer->normalize($broken);
    }
}
