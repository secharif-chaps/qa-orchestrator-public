<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Document\Fingerprinting\ShingleExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShingleExtractor::class)]
class ShingleExtractorTest extends TestCase
{
    private ShingleExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ShingleExtractor(new ScriptDetector());
    }

    // ─── Word-shingle mode (Latin / space-separated) ─────────────────────

    public function testEmptyInputProducesNoShingles(): void
    {
        self::assertSame([], $this->extractor->extract(''));
    }

    public function testWhitespaceOnlyInputProducesNoShingles(): void
    {
        // `TextNormalizer` should never emit pure whitespace, but defend
        // against a misuse that would otherwise produce shingles built
        // from empty strings.
        self::assertSame([], $this->extractor->extract('   '));
    }

    public function testSingleWordReturnedAsLoneShingle(): void
    {
        // Below the 3-word threshold → degenerate fallback so the document
        // is still represented by something hashable downstream.
        self::assertSame(['hello'], $this->extractor->extract('hello'));
    }

    public function testTwoWordsBelowThresholdReturnedAsLoneShingle(): void
    {
        self::assertSame(['hello world'], $this->extractor->extract('hello world'));
    }

    public function testProducesThreeWordShingles(): void
    {
        $shingles = $this->extractor->extract('the quick brown fox jumps');

        self::assertSame(['the quick brown', 'quick brown fox', 'brown fox jumps'], $shingles);
    }

    public function testDeduplicatesIdenticalShingles(): void
    {
        $shingles = $this->extractor->extract('a b c a b c');

        self::assertSame(['a b c', 'b c a', 'c a b'], $shingles);
    }

    public function testWorksOnFrenchLatinText(): void
    {
        $shingles = $this->extractor->extract('le président français visite paris');

        self::assertCount(3, $shingles);
        self::assertContains('le président français', $shingles);
        self::assertContains('président français visite', $shingles);
        self::assertContains('français visite paris', $shingles);
    }

    public function testWorksOnCyrillicText(): void
    {
        $shingles = $this->extractor->extract('москва столица россии большой город');

        self::assertCount(3, $shingles);
        self::assertContains('москва столица россии', $shingles);
    }

    // ─── Char-ngram mode (CJK) ───────────────────────────────────────────

    public function testProducesFourCharNgramsOnPureCjk(): void
    {
        $shingles = $this->extractor->extract('東京都は日本の首都です');

        self::assertContains('東京都は', $shingles);
        self::assertContains('京都は日', $shingles);
        self::assertContains('都は日本', $shingles);
        self::assertContains('は日本の', $shingles);
    }

    public function testCjkBelowFourCharsReturnedAsLoneShingle(): void
    {
        self::assertSame(['北京'], $this->extractor->extract('北京'));
    }

    public function testCjkExactlyFourCharsProducesSingleNgram(): void
    {
        self::assertSame(['東京都は'], $this->extractor->extract('東京都は'));
    }

    public function testCjkInternalSpacesAreCollapsedBeforeNgramming(): void
    {
        // CJK pages sometimes contain stray ASCII spaces between glyphs;
        // we must not produce shingles that depend on those.
        $with = $this->extractor->extract('東 京 都 は 日 本');
        $without = $this->extractor->extract('東京都は日本');

        self::assertSame($without, $with);
    }

    public function testJapaneseSentenceProducesExpectedNgramCount(): void
    {
        // 11-char input → 11 - 4 + 1 = 8 distinct 4-grams.
        $shingles = $this->extractor->extract('東京都は日本の首都です');

        self::assertCount(8, $shingles);
    }

    public function testKoreanHangulProducesCharNgrams(): void
    {
        $shingles = $this->extractor->extract('서울은한국의수도입니다');

        self::assertContains('서울은한', $shingles);
        self::assertContains('울은한국', $shingles);
    }

    public function testChineseTextProducesCharNgrams(): void
    {
        $shingles = $this->extractor->extract('北京是中国的首都');

        self::assertContains('北京是中', $shingles);
        self::assertContains('京是中国', $shingles);
    }

    // ─── Mixed-script handling ───────────────────────────────────────────

    public function testMostlyLatinWithMinorityCjkUsesWordShingles(): void
    {
        $shingles = $this->extractor->extract('the japanese capital is 東京都 and it is huge');

        // Expected word-shingle output → `'east-asian fragment 東京都'` stays
        // glued inside one of the word shingles.
        self::assertContains('the japanese capital', $shingles);
        self::assertContains('capital is 東京都', $shingles);
    }

    public function testMostlyCjkWithMinorityLatinUsesCharNgrams(): void
    {
        $shingles = $this->extractor->extract('東京都は日本の首都です tokyo');

        // Char-ngram mode: the latin fragment becomes part of 4-char windows.
        self::assertContains('東京都は', $shingles);
    }

    // ─── Performance / stability ─────────────────────────────────────────

    public function testHandlesLongCjkInputLinearly(): void
    {
        // The naive `mb_substr()` loop would be O(N²). With `mb_str_split`
        // it's O(N) — guard against a regression on this hot path.
        $longCjk = str_repeat('日本語のテキスト', 5_000); // 40 000 chars

        $start = microtime(true);
        $shingles = $this->extractor->extract($longCjk);
        $elapsed = microtime(true) - $start;

        self::assertNotEmpty($shingles);
        self::assertLessThan(2.0, $elapsed, 'CJK shingle extraction took too long');
    }

    public function testHandlesLongLatinInputLinearly(): void
    {
        $longLatin = trim(str_repeat('the quick brown fox jumps over the lazy dog ', 5_000));

        $start = microtime(true);
        $shingles = $this->extractor->extract($longLatin);
        $elapsed = microtime(true) - $start;

        self::assertNotEmpty($shingles);
        self::assertLessThan(2.0, $elapsed, 'Latin shingle extraction took too long');
    }

    // ─── Determinism / idempotence ───────────────────────────────────────

    public function testProducesSameOutputAcrossRepeatedCallsLatin(): void
    {
        // Hashing in `SimHashGenerator` / `MinHashGenerator` relies on
        // shingle order being stable across calls.
        $a = $this->extractor->extract('the quick brown fox jumps over the lazy dog');
        $b = $this->extractor->extract('the quick brown fox jumps over the lazy dog');

        self::assertSame($a, $b);
    }

    public function testProducesSameOutputAcrossRepeatedCallsCjk(): void
    {
        $a = $this->extractor->extract('東京都は日本の首都です');
        $b = $this->extractor->extract('東京都は日本の首都です');

        self::assertSame($a, $b);
    }

    public function testReextractingFromShinglesIsConsistent(): void
    {
        // Feeding `implode(' ', extract($x))` back through the extractor
        // would produce a deterministic result — useful when chaining
        // pipeline stages.
        $first = $this->extractor->extract('the quick brown fox jumps over the lazy dog');
        $second = $this->extractor->extract(implode(' ', $first));

        self::assertSame($second, $this->extractor->extract(implode(' ', $first)));
    }

    public function testInputWithLeadingTrailingSpacesYieldsSameShinglesAsTrimmed(): void
    {
        // Defensive: `TextNormalizer` always trims, but a misuse must
        // not produce different shingles for "trimmed" vs "untrimmed".
        $trimmed = $this->extractor->extract('hello world');
        $padded = $this->extractor->extract('   hello world   ');

        self::assertSame($trimmed, $padded);
    }

    // ─── Mixed-script edge cases ─────────────────────────────────────────

    public function testMixedScriptInsideSingleWord(): void
    {
        // "microsoft中国" is a single word post-normalisation. With <30%
        // CJK overall, we go through the word-shingle path, so the mixed
        // word stays glued.
        $shingles = $this->extractor->extract('about microsoft中国 launches new product');

        self::assertContains('about microsoft中国 launches', $shingles);
    }

    public function testThresholdBoundaryRoutingIsDeterministic(): void
    {
        // A short text with a mixed-script word should route to a single
        // path, not flip-flop. We don't care which one — just that it's
        // stable and produces shingles.
        $shingles = $this->extractor->extract('東京都 tokyo capital city');

        self::assertNotEmpty($shingles);
        self::assertSame($shingles, $this->extractor->extract('東京都 tokyo capital city'));
    }
}
