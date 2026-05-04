<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\ScriptDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScriptDetector::class)]
class ScriptDetectorTest extends TestCase
{
    private ScriptDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ScriptDetector();
    }

    public function testEmptyTextIsNotCjkDominant(): void
    {
        self::assertFalse($this->detector->isCjkDominant(''));
    }

    public function testPureLatinIsNotCjkDominant(): void
    {
        self::assertFalse($this->detector->isCjkDominant('the quick brown fox jumps over the lazy dog'));
    }

    public function testPureCyrillicIsNotCjkDominant(): void
    {
        self::assertFalse($this->detector->isCjkDominant('Москва столица России'));
    }

    public function testPureArabicIsNotCjkDominant(): void
    {
        self::assertFalse($this->detector->isCjkDominant('القاهرة عاصمة مصر'));
    }

    public function testPureHanIsCjkDominant(): void
    {
        self::assertTrue($this->detector->isCjkDominant('北京是中国的首都'));
    }

    public function testPureHiraganaIsCjkDominant(): void
    {
        self::assertTrue($this->detector->isCjkDominant('こんにちはせかい'));
    }

    public function testPureKatakanaIsCjkDominant(): void
    {
        self::assertTrue($this->detector->isCjkDominant('コンピュータ'));
    }

    public function testPureHangulIsCjkDominant(): void
    {
        self::assertTrue($this->detector->isCjkDominant('서울은한국의수도입니다'));
    }

    public function testMixedJapaneseSentenceIsCjkDominant(): void
    {
        // Real-world Japanese mixes Han + Hiragana + Katakana.
        self::assertTrue($this->detector->isCjkDominant('東京都は日本の首都です'));
    }

    public function testLatinWithMinorityCjkIsNotDominant(): void
    {
        // 5 CJK chars out of ~80 → ~6% → below threshold.
        self::assertFalse($this->detector->isCjkDominant(
            'The Japanese capital is 東京都 and it is the most populous metropolitan area in the world',
        ));
    }

    public function testCjkWithMinorityLatinIsDominant(): void
    {
        // Mostly CJK with a brand name → still above 30%.
        self::assertTrue($this->detector->isCjkDominant('東京都は日本の首都です Tokyo'));
    }

    public function testThresholdIsConfigurable(): void
    {
        $text = 'hello 北京 world'; // 2 CJK / 14 chars ≈ 14%

        self::assertFalse($this->detector->isCjkDominant($text, 0.30));
        self::assertTrue($this->detector->isCjkDominant($text, 0.10));
    }

    public function testWhitespaceOnlyIsNotCjkDominant(): void
    {
        self::assertFalse($this->detector->isCjkDominant("   \n\t  "));
    }

    public function testCjkExtensionAIsDetected(): void
    {
        // U+3400 is the start of CJK Extension A.
        $text = "\u{3400}\u{3401}\u{3402}\u{3403}";

        self::assertTrue($this->detector->isCjkDominant($text));
    }

    public function testHandlesLongInputWithoutCrash(): void
    {
        $longCjk = str_repeat('日本語のテキスト', 5_000);

        self::assertTrue($this->detector->isCjkDominant($longCjk));
    }

    // ─── Threshold boundary behaviour ────────────────────────────────────

    public function testThresholdInclusiveAtBoundary(): void
    {
        // Exactly 3 CJK chars in 10 chars = 30%. Threshold uses `>=` so
        // the boundary is inclusive → CJK-dominant.
        $text = '北京abcdefg'; // 3 CJK / wait, this is 2 CJK + 7 latin = 9 chars total
        // Build a deterministic 30% case: 3 CJK + 7 ASCII = 10 chars total.
        $text = '北京东abcdefg';

        self::assertTrue($this->detector->isCjkDominant($text, 0.30));
    }

    public function testThresholdExclusiveJustBelowBoundary(): void
    {
        // 2 CJK / 10 chars = 20% — below 30% threshold.
        $text = '北京abcdefgh';

        self::assertFalse($this->detector->isCjkDominant($text, 0.30));
    }

    public function testThresholdZeroAcceptsAnyText(): void
    {
        // Any non-empty text has CJK ratio ≥ 0, so threshold=0 always
        // returns true (even on pure Latin).
        self::assertTrue($this->detector->isCjkDominant('hello world', 0.0));
    }

    public function testThresholdOneRequiresPureCjk(): void
    {
        self::assertTrue($this->detector->isCjkDominant('北京东', 1.0));
        self::assertFalse($this->detector->isCjkDominant('北京a', 1.0));
    }

    public function testThresholdAboveOneIsImpossibleToMeet(): void
    {
        self::assertFalse($this->detector->isCjkDominant('北京东', 1.5));
    }

    // ─── Edge cases on character classes ─────────────────────────────────

    public function testHalfWidthKatakanaIsDetectedAsCjk(): void
    {
        // Half-width Katakana (U+FF65–FF9F) — `\p{Katakana}` covers them.
        self::assertTrue($this->detector->isCjkDominant("\u{FF76}\u{FF80}\u{FF80}\u{FF85}"));
    }

    public function testEmojiIsNotCjk(): void
    {
        // Pictographic codepoints look "Asian" but are not CJK script.
        self::assertFalse($this->detector->isCjkDominant('🎉🎊🎈🎁'));
    }

    public function testIdeographicPunctuationCountsAsCjk(): void
    {
        // PCRE/Unicode classifies U+3001 (、) and U+3002 (。) as Han via
        // script extensions, so `\p{Han}` matches them. We accept this
        // behaviour: a `、`-rich text is contextually CJK. Anchored as a
        // regression marker so any future move to a stricter classifier
        // (Script_Extensions=Han only) is intentional.
        self::assertTrue($this->detector->isCjkDominant("\u{3001}\u{3002}\u{3001}"));
    }

    public function testNumbersDoNotInflateCjkRatio(): void
    {
        // Latin digits + a couple of CJK chars must stay below threshold.
        self::assertFalse($this->detector->isCjkDominant('2026 GDP 北京 12345'));
    }
}
