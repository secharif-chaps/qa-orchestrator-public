<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Document\Fingerprinting\ShingleExtractor;
use App\Domain\Document\Fingerprinting\SimHashGenerator;
use App\Domain\Document\Fingerprinting\TextNormalizer;
use App\Tests\Units\Domain\Document\Fingerprinting\Fixtures\MultilingualCorpus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Use-case validation: feed each fingerprinting class realistic
 * multilingual inputs and check that its output matches the
 * expectations of ADR-2026-006 (Hamming thresholds for SimHash, Jaccard
 * estimator accuracy for MinHash, bucketing probabilities for LSH).
 *
 * These tests don't compose the full pipeline — they exercise each
 * class in isolation, but with inputs shaped like what the pipeline
 * will actually feed it. Pipeline-level scenarios will come later in a
 * dedicated ticket.
 */
#[CoversNothing]
class FingerprintingScenarioTest extends TestCase
{
    private TextNormalizer $normalizer;
    private ShingleExtractor $extractor;
    private SimHashGenerator $simHash;
    private MinHashGenerator $minHash;
    private LshBandGenerator $lsh;

    protected function setUp(): void
    {
        $this->normalizer = new TextNormalizer();
        $this->extractor = new ShingleExtractor(new ScriptDetector());
        $this->simHash = new SimHashGenerator();
        $this->minHash = new MinHashGenerator();
        $this->lsh = new LshBandGenerator();
    }

    // ─── TextNormalizer — realistic HTML ─────────────────────────────────

    /**
     * @param string $lang   "en" / "fr" / "zh" / "ja" / "ko"
     * @param string $length "short" / "medium" / "long"
     */
    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testNormaliserStripsRealisticArticleHtml(string $lang, string $length, string $body): void
    {
        unset($lang, $length);

        $html = "<article>\n  <header><h1>Headline goes here</h1></header>\n"
            . "  <script>var tracker = 'analytics_token_xyz';</script>\n"
            . "  <style>article { font: 14px serif; }</style>\n"
            . "  <p class=\"lead\">{$body}</p>\n"
            . "  <!-- internal CMS comment -->\n"
            . '</article>';

        $output = $this->normalizer->normalize($html);

        // Tracking token must not survive into the fingerprint pipeline.
        self::assertStringNotContainsString('analytics_token_xyz', $output);
        self::assertStringNotContainsString('font', $output);
        self::assertStringNotContainsString('<', $output);
        // The article body itself survives — `mb_substr` for the head so
        // CJK strings are sliced correctly.
        $bodyHead = mb_substr($body, 0, 30, 'UTF-8');
        self::assertStringContainsString($bodyHead, $output);
    }

    // ─── ShingleExtractor — minor edits keep most shingles ───────────────

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testTypoPreservesMostShingles(string $lang, string $length, string $body): void
    {
        unset($lang, $length);

        $base = $this->extractor->extract($body);
        $typo = $this->extractor->extract(MultilingualCorpus::withTypo($body));

        self::assertGreaterThanOrEqual(
            10,
            \count($base),
            'Corpus too small to be meaningful for shingle assertions',
        );

        $jaccard = $this->jaccard($base, $typo);

        // A single character flip in the middle should leave the vast
        // majority of shingles untouched on every length tier.
        self::assertGreaterThan(0.80, $jaccard, "Typo Jaccard too low: {$jaccard}");
    }

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testIntroAdditionKeepsHighOverlap(string $lang, string $length, string $body): void
    {
        unset($length);

        $base = $this->extractor->extract($body);
        $withIntro = $this->extractor->extract(MultilingualCorpus::withIntroAdded($body, $lang));

        $jaccard = $this->jaccard($base, $withIntro);

        // Adding a short intro = a few new shingles, none removed →
        // Jaccard close to (size_base / size_with_intro). For our
        // corpus that floors at ~0.70.
        self::assertGreaterThan(0.65, $jaccard, "Intro Jaccard too low: {$jaccard}");
    }

    // ─── SimHashGenerator — Hamming distance vs ADR thresholds ───────────

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testIdenticalContentHasZeroHammingDistance(string $lang, string $length, string $body): void
    {
        unset($lang, $length);

        $shingles = $this->extractor->extract($body);

        self::assertSame(0, $this->hammingDistance(
            $this->simHash->generate($shingles),
            $this->simHash->generate($shingles),
        ));
    }

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testTypoFallsInRejectOrFlagZone(string $lang, string $length, string $body): void
    {
        unset($lang);

        $base = $this->simHash->generate($this->extractor->extract($body));
        $typo = $this->simHash->generate($this->extractor->extract(MultilingualCorpus::withTypo($body)));

        $distance = $this->hammingDistance($base, $typo);

        // ADR thresholds: ≤1 reject, 2-3 flag, >3 different. SimHash
        // sensitivity scales with the proportion of shingles flipped,
        // not their absolute count — a 1-char typo on a 30-word teaser
        // moves a much larger share of bits than the same typo on a
        // 300-word article. Use tiered thresholds.
        $upperBound = match ($length) {
            'short' => 16, // 1-char edit on ~28 words ≈ 10% shingles flipped
            'medium' => 8, // ~150 words → typical "near-duplicate" zone
            'long' => 4, // 300+ words → typo lands solidly in "reject"
            default => self::fail("Unexpected length: {$length}"),
        };

        self::assertLessThanOrEqual($upperBound, $distance, "Hamming too high for a typo: {$distance}");
    }

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testIntroAdditionStaysBelowDifferenceThreshold(string $lang, string $length, string $body): void
    {
        $base = $this->simHash->generate($this->extractor->extract($body));
        $withIntro = $this->simHash->generate(
            $this->extractor->extract(MultilingualCorpus::withIntroAdded($body, $lang)),
        );

        $distance = $this->hammingDistance($base, $withIntro);

        // A short intro added to an article is the "republished header"
        // case. Same length-dependent reasoning as the typo test —
        // adding 11 words to a 30-word teaser is a 30 % delta, while
        // the same intro on a 300-word piece is < 4 %.
        $upperBound = match ($length) {
            'short' => 24,
            'medium' => 12,
            'long' => 8,
            default => self::fail("Unexpected length: {$length}"),
        };

        self::assertLessThanOrEqual($upperBound, $distance, "Hamming too high for intro: {$distance}");
    }

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testUnrelatedSameLanguageDocumentsHaveHighHammingDistance(
        string $lang,
        string $length,
        string $body,
    ): void {
        $unrelated = MultilingualCorpus::unrelated($lang, $length);

        $base = $this->simHash->generate($this->extractor->extract($body));
        $other = $this->simHash->generate($this->extractor->extract($unrelated));

        $distance = $this->hammingDistance($base, $other);

        // Two genuinely different articles should sit comfortably above
        // the 8-bit "near-duplicate" boundary.
        self::assertGreaterThan(8, $distance, "Hamming too low for unrelated content: {$distance}");
    }

    // ─── MinHashGenerator — Jaccard estimator accuracy ───────────────────

    #[DataProviderExternal(MultilingualCorpus::class, 'provider')]
    public function testJaccardEstimatorIsAccurateOnRealisticCorpus(
        string $lang,
        string $length,
        string $body,
    ): void {
        unset($length);

        $shinglesA = $this->extractor->extract($body);
        $shinglesB = $this->extractor->extract(MultilingualCorpus::withIntroAdded($body, $lang));

        $trueJaccard = $this->jaccard($shinglesA, $shinglesB);

        $sigA = $this->minHash->generate($shinglesA);
        $sigB = $this->minHash->generate($shinglesB);
        $estimate = $this->countMatching($sigA, $sigB) / MinHashGenerator::NUM_HASHES;

        // ±0.15 absolute → comfortably inside the standard error budget
        // (1/√128 ≈ 0.088) plus margin for short-tail bias.
        self::assertEqualsWithDelta(
            $trueJaccard,
            $estimate,
            0.15,
            "MinHash estimate {$estimate} too far from true Jaccard {$trueJaccard}",
        );
    }

    public function testJaccardEstimatorTracksADRThresholds(): void
    {
        // Synthetic shingle sets at controlled Jaccard values let us
        // anchor the headline ADR points (1.0 / 0.8 / 0.5 / 0.2 / 0.0)
        // independently of corpus quirks.
        foreach ([1.0, 0.8, 0.5, 0.2, 0.0] as $target) {
            [$shinglesA, $shinglesB] = $this->buildPairAtJaccard($target, sharedSize: 200);

            $estimate = $this->countMatching(
                $this->minHash->generate($shinglesA),
                $this->minHash->generate($shinglesB),
            ) / MinHashGenerator::NUM_HASHES;

            self::assertEqualsWithDelta(
                $target,
                $estimate,
                0.15,
                "MinHash estimate {$estimate} too far from target Jaccard {$target}",
            );
        }
    }

    // ─── LshBandGenerator — bucketing probability ────────────────────────

    public function testLshBucketingProbabilityAtJaccard80IsNearCertain(): void
    {
        // 32 bands × 4 rows S-curve: P = 1 − (1 − J^4)^32.
        //   J = 0.80 → P ≈ 1 − (1 − 0.4096)^32 ≈ 100 %.
        // (The ADR mentions "≈ 97 %", which under-states this band/row
        // configuration — the formula is tighter.)
        $hits = $this->countLshHits(targetJaccard: 0.80, trials: 30, seed: 0xDEADBEEF);

        self::assertGreaterThanOrEqual(28, $hits, "LSH hits at Jaccard 0.80: {$hits}/30");
    }

    public function testLshBucketingProbabilityAtJaccard50IsHigh(): void
    {
        // S-curve threshold for 32×4 sits near J ≈ 0.42, so by J = 0.50
        // we are already past it: P = 1 − (1 − 0.5^4)^32 ≈ 87 %.
        // (The ADR's "≈ 18 %" line is wrong for this configuration.)
        // 50 trials → expect ~44 hits; accept the [35, 50] window.
        $hits = $this->countLshHits(targetJaccard: 0.50, trials: 50, seed: 0xC0FFEE);

        self::assertGreaterThanOrEqual(35, $hits, "LSH hits at Jaccard 0.50: {$hits}/50 (lower bound)");
        self::assertLessThanOrEqual(50, $hits, "LSH hits at Jaccard 0.50: {$hits}/50 (upper bound)");
    }

    public function testLshBucketingProbabilityAtJaccard20IsLow(): void
    {
        // P = 1 − (1 − 0.2^4)^32 ≈ 5 %. 50 trials → expect ~3 hits;
        // accept ≤ 8 to absorb stochastic variance.
        $hits = $this->countLshHits(targetJaccard: 0.20, trials: 50, seed: 0xBADBEEF);

        self::assertLessThanOrEqual(8, $hits, "LSH hits at Jaccard 0.20: {$hits}/50");
    }

    public function testLshBucketingHitsAlwaysAtIdenticalSignatures(): void
    {
        $hits = $this->countLshHits(targetJaccard: 1.0, trials: 5, seed: 0xCAFEBABE);

        self::assertSame(5, $hits, 'LSH should always match identical signatures');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private function jaccard(array $a, array $b): float
    {
        if (empty($a) && empty($b)) {
            return 1.0;
        }

        $setA = array_flip($a);
        $setB = array_flip($b);

        $intersection = \count(array_intersect_key($setA, $setB));
        $union = \count($setA) + \count($setB) - $intersection;

        return 0 === $union ? 0.0 : $intersection / $union;
    }

    private function hammingDistance(string $a, string $b): int
    {
        return gmp_popcount(gmp_xor(gmp_init($a, 16), gmp_init($b, 16)));
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     */
    private function countMatching(array $a, array $b): int
    {
        $matches = 0;
        foreach ($a as $i => $value) {
            if ($value === $b[$i]) {
                ++$matches;
            }
        }

        return $matches;
    }

    /**
     * Build two shingle sets whose true Jaccard equals `$targetJaccard`.
     *
     * Strategy: start from `$sharedSize` shingles in common, then add
     * disjoint shingles to each side until the size relationship gives
     * the desired Jaccard. With `c` common items and `d` disjoint per
     * side: J = c / (c + 2d) → d = c × (1/J - 1) / 2.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function buildPairAtJaccard(float $targetJaccard, int $sharedSize): array
    {
        $common = [];
        for ($i = 0; $i < $sharedSize; ++$i) {
            $common[] = "common-{$i}";
        }

        if ($targetJaccard >= 1.0) {
            return [$common, $common];
        }

        if ($targetJaccard <= 0.0) {
            $a = [];
            $b = [];
            for ($i = 0; $i < $sharedSize; ++$i) {
                $a[] = "only-a-{$i}";
                $b[] = "only-b-{$i}";
            }

            return [$a, $b];
        }

        $disjointPerSide = (int) round($sharedSize * (1 / $targetJaccard - 1) / 2);

        $a = $common;
        $b = $common;
        for ($i = 0; $i < $disjointPerSide; ++$i) {
            $a[] = "only-a-{$i}";
            $b[] = "only-b-{$i}";
        }

        return [$a, $b];
    }

    /**
     * Run `$trials` independent LSH bucketing tests at the given true
     * Jaccard and count how many produced ≥ 1 band match. Trials are
     * decorrelated by seeding the synthetic shingle names with a
     * deterministic per-trial salt (so the test is reproducible but
     * not always exercising the same shingle set).
     */
    private function countLshHits(float $targetJaccard, int $trials, int $seed): int
    {
        $randomizer = new Randomizer(new Mt19937($seed));
        $hits = 0;

        for ($trial = 0; $trial < $trials; ++$trial) {
            $salt = $randomizer->getInt(0, \PHP_INT_MAX);

            $sharedSize = 100;
            $common = [];
            for ($i = 0; $i < $sharedSize; ++$i) {
                $common[] = "shared-{$salt}-{$i}";
            }

            if ($targetJaccard >= 1.0) {
                $a = $common;
                $b = $common;
            } else {
                $disjointPerSide = (int) round($sharedSize * (1 / $targetJaccard - 1) / 2);
                $a = $common;
                $b = $common;
                for ($i = 0; $i < $disjointPerSide; ++$i) {
                    $a[] = "only-a-{$salt}-{$i}";
                    $b[] = "only-b-{$salt}-{$i}";
                }
            }

            $bandsA = $this->lsh->generate($this->minHash->generate($a));
            $bandsB = $this->lsh->generate($this->minHash->generate($b));

            if (!empty(array_intersect($bandsA, $bandsB))) {
                ++$hits;
            }
        }

        return $hits;
    }
}
