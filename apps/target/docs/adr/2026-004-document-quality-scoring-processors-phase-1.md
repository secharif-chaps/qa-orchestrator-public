# ADR-2026-004: Document Quality Scoring Processors Phase 1

| Status   | Date       | Author                    |
| -------- | ---------- | ------------------------- |
| Accepted | 2026-01-11 | Frédéric Fayard-Le Barzic |

## Context

ADR-2026-003 established the Document Quality Pipeline architecture with the Pipeline Pattern and signal accumulation.
This ADR defines the **first set of concrete scoring processors** to implement for Phase 1 deployment, targeting maximum
noise reduction with minimal complexity.

### Goals for Phase 1

1. **Quick wins**: Implement processors with highest impact-to-effort ratio
2. **No external dependencies**: Avoid paid APIs, heavy infrastructure in initial phase
3. **WatchFile control**: Honor client-specific trusted/blocked domain lists
4. **Traceability**: Every decision must have a clear, explainable reason

### Signal Selection Criteria

Based on analysis from reflexion documents, processors were prioritized by:

| Criteria            | Weight | Description                                           |
| ------------------- | ------ | ----------------------------------------------------- |
| Impact on noise     | 40%    | How much irrelevant content does this processor flag? |
| Implementation cost | 30%    | Time to implement, external dependencies required     |
| False positive risk | 20%    | Risk of filtering legitimate content                  |
| Maintainability     | 10%    | Ongoing maintenance burden                            |

## Decision

Implement **8 scoring processors** in Phase 1, organized by priority tier:

### Priority Tiers

| Tier | Processors                                                | Characteristics                  |
| ---- | --------------------------------------------------------- | -------------------------------- |
| P0   | WatchFile Override, TLD Reputation, HTTPS, Adblock Domain | Immediate execution, early exit  |
| P1   | Word Count, Content Ratio, URL Patterns, Publication Date | Content heuristics, no API calls |

---

## Score Calculation Methodology

### Category-Based Scoring with Minimum

The final quality score is computed as the **minimum of category-level weighted averages**:

```
categoryScore[c] = Σ(signal[i].value × signal[i].weight) / Σ(signal[i].weight)
                   for all signals i in category c

finalScore = min(categoryScore[c]) for all categories c with signals
```

**Why not a global weighted average?** A global average allows strong categories to compensate for weak ones.
A document from a known spam TLD (infrastructure score 0.15) with well-written content (content score 0.85) would
score ~0.71 with a global average — passing the threshold. With category-based min, the infrastructure score of 0.42
becomes the final score, correctly flagging the document.

### Categories

| Category                 | Purpose                                | Signals (Phase 1)                           |
| ------------------------ | -------------------------------------- | ------------------------------------------- |
| **infrastructure_trust** | Is the source technically trustworthy? | `tld_reputation`, `https`, `adblock_domain` |
| **content_quality**      | Is the content substantial?            | `word_count`, `content_ratio`               |
| **metadata**             | Does the metadata look legitimate?     | `url_pattern`, `publication_date`           |

`watchfile_override` is handled separately as an early decision (blocked) or a strong signal injected into
`infrastructure_trust` (trusted).

### Key Properties

| Property                           | Behavior                                                        |
| ---------------------------------- | --------------------------------------------------------------- |
| **No cross-category compensation** | A weak infrastructure score cannot be saved by good content     |
| **Within-category averaging**      | Signals within the same category balance each other as expected |
| **Extensible**                     | Adding a processor to a category refines that category's score  |
| **Adding categories**              | New categories add a new dimension — more stringent overall     |
| **Final score range**              | Always 0.0 - 1.0 (min of averages, each average is 0.0 - 1.0)   |

### Example Calculation

Given a document on a suspicious TLD but with good content:

**Infrastructure Trust** (weighted average within category):

| Signal           | Value | Weight | Contribution |
| ---------------- | ----- | ------ | ------------ |
| `tld_reputation` | 0.15  | 1.0    | 0.15 × 1.0   |
| `https`          | 0.80  | 0.5    | 0.80 × 0.5   |
| `adblock_domain` | 0.85  | 0.8    | 0.85 × 0.8   |

Category score: (0.15 + 0.40 + 0.68) / (1.0 + 0.5 + 0.8) = 1.23 / 2.3 = **0.53**

**Content Quality** (weighted average within category):

| Signal          | Value | Weight | Contribution |
| --------------- | ----- | ------ | ------------ |
| `word_count`    | 0.85  | 1.5    | 0.85 × 1.5   |
| `content_ratio` | 0.70  | 1.0    | 0.70 × 1.0   |

Category score: (1.275 + 0.70) / (1.5 + 1.0) = 1.975 / 2.5 = **0.79**

**Metadata** (weighted average within category):

| Signal             | Value | Weight | Contribution |
| ------------------ | ----- | ------ | ------------ |
| `url_pattern`      | 0.70  | 0.8    | 0.70 × 0.8   |
| `publication_date` | 0.80  | 0.7    | 0.80 × 0.7   |

Category score: (0.56 + 0.56) / (0.8 + 0.7) = 1.12 / 1.5 = **0.75**

**Final score: min(0.53, 0.79, 0.75) = 0.53 → `LOW_QUALITY`**

The suspicious TLD pulls the infrastructure category down, and the min ensures it determines the final outcome —
despite excellent content and metadata scores. With a global weighted average, this same document would have scored
**0.71 → `ACCEPTED`**.

### Weight Assignment Guidelines

Weights control relative importance **within a category**, not across categories:

| Weight | Interpretation             | Use Case                                |
| ------ | -------------------------- | --------------------------------------- |
| 2.0    | Critical / High confidence | Dominant signal within its category     |
| 1.5    | Important                  | Strong signal, proven reliability       |
| 1.0    | Standard                   | Default for most signals                |
| 0.8    | Moderate                   | Useful but not decisive within category |
| 0.5    | Weak                       | Supplementary signal, high noise        |

### Adding a New Processor - Checklist

1. **Assign category** from `SignalCategory` enum (or propose a new category if none fits)
2. **Assign weight** based on importance within the category (no cross-category rebalancing needed)
3. **Document rationale** for category and weight selection in processor specification
4. **Test impact** on sample dataset — verify the category score behaves as expected
5. **Update Signal Weight Summary** table

---

### Processor Specifications

---

## P0 Processors: Critical Path

### 1. WatchFileOverrideProcessor

**Purpose**: Honor WatchFile-specific trusted/blocked domain lists (early exit).

**Priority**: 100 (runs first)

**Behavior**:

- Blocked domain → Early decision: `REJECTED`, reason: "Domain blocked by WatchFile configuration"
- Trusted domain → High signal value (0.95), skip remaining domain-based filters

```php
final readonly class WatchFileOverrideProcessor implements DocumentProcessorInterface
{
    private const float TRUSTED_SCORE = 0.95;
    private const float WEIGHT = 2.0;

    public function priority(): int
    {
        return 100;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $domain = $this->extractDomain($context->document->getUrl());
        $config = $context->watchFile->getQualityConfig();

        // Check blocked domains first (early exit)
        if ($this->isDomainBlocked($domain, $config->blockedDomains)) {
            return $context->withEarlyDecision(
                QualityDecision::REJECTED,
                sprintf('Domain %s is blocked by WatchFile configuration', $domain)
            );
        }

        // Check trusted domains
        if ($this->isDomainTrusted($domain, $config->trustedDomains)) {
            return $context->withSignal('watchfile_override', new Signal(
                value: self::TRUSTED_SCORE,
                weight: self::WEIGHT,
                reason: sprintf('Trusted domain: %s', $domain),
            ));
        }

        // No override, continue to next filters
        return $context;
    }

    private function isDomainBlocked(string $domain, array $blockedDomains): bool
    {
        foreach ($blockedDomains as $pattern) {
            if ($this->matchesDomainPattern($domain, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function isDomainTrusted(string $domain, array $trustedDomains): bool
    {
        foreach ($trustedDomains as $pattern) {
            if ($this->matchesDomainPattern($domain, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function matchesDomainPattern(string $domain, string $pattern): bool
    {
        // Support wildcards: *.gov.fr, *.edu
        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1); // .gov.fr
            return str_ends_with($domain, $suffix);
        }
        return $domain === $pattern;
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return strtolower(preg_replace('/^www\./i', '', $host));
    }
}
```

**Signal**: `watchfile_override`

**Test Cases**:

- Domain in `blockedDomains` → `REJECTED` early decision
- Domain matches `*.gov.fr` in `trustedDomains` → Signal 0.95
- Domain not in any list → No signal added, continue pipeline

---

### 2. TldReputationProcessor

**Purpose**: Score documents based on TLD trustworthiness.

**Priority**: 95

**Rationale**: Certain TLDs (`.tk`, `.ml`, `.ga`, `.gq`, `.cf`) are heavily associated with spam, phishing, and
low-quality content farms. Conversely, ccTLDs like `.gov`, `.edu`, and country codes have implicit trust signals.

**Behavior**:

- Suspicious TLDs → Low signal (0.2-0.4)
- Trusted TLDs → High signal (0.85-0.95)
- Neutral TLDs → Medium signal (0.6)

```php
final readonly class TldReputationProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 1.0;

    // TLDs with known spam/abuse problems
    private const array SUSPICIOUS_TLDS = [
        'tk' => 0.15,   // Tokelau - massive spam abuse
        'ml' => 0.20,   // Mali - high spam rate
        'ga' => 0.20,   // Gabon - high spam rate
        'cf' => 0.20,   // Central African Republic - high spam rate
        'gq' => 0.20,   // Equatorial Guinea - high spam rate
        'top' => 0.30,  // Generic - popular for spam
        'xyz' => 0.35,  // Generic - mixed quality
        'click' => 0.25,// Generic - affiliate spam
        'link' => 0.30, // Generic - link farms
        'work' => 0.35, // Generic - mixed quality
        'win' => 0.25,  // Generic - gambling/scam
        'loan' => 0.20, // Generic - scam heavy
        'download' => 0.25, // Generic - malware
    ];

    // TLDs with implicit trust
    private const array TRUSTED_TLDS = [
        'gov' => 0.95,      // Government
        'edu' => 0.90,      // Education
        'mil' => 0.95,      // Military
        'int' => 0.90,      // International organizations
        'gouv.fr' => 0.95,  // French government
        'gov.uk' => 0.95,   // UK government
        'europa.eu' => 0.90,// EU institutions
    ];

    private const float NEUTRAL_SCORE = 0.60;

    public function priority(): int
    {
        return 95;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $url = $context->document->getUrl();
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            return $context->withSignal('tld_reputation', new Signal(
                value: 0.3,
                weight: self::WEIGHT,
                reason: 'Unable to parse URL host',
            ));
        }

        $tld = $this->extractTld($host);
        $extendedTld = $this->extractExtendedTld($host);

        // Check extended TLDs first (e.g., gouv.fr, gov.uk)
        if (isset(self::TRUSTED_TLDS[$extendedTld])) {
            return $context->withSignal('tld_reputation', new Signal(
                value: self::TRUSTED_TLDS[$extendedTld],
                weight: self::WEIGHT,
                reason: sprintf('Trusted TLD: .%s', $extendedTld),
            ));
        }

        // Check base TLD
        if (isset(self::TRUSTED_TLDS[$tld])) {
            return $context->withSignal('tld_reputation', new Signal(
                value: self::TRUSTED_TLDS[$tld],
                weight: self::WEIGHT,
                reason: sprintf('Trusted TLD: .%s', $tld),
            ));
        }

        if (isset(self::SUSPICIOUS_TLDS[$tld])) {
            return $context->withSignal('tld_reputation', new Signal(
                value: self::SUSPICIOUS_TLDS[$tld],
                weight: self::WEIGHT,
                reason: sprintf('Suspicious TLD: .%s (high spam association)', $tld),
            ));
        }

        return $context->withSignal('tld_reputation', new Signal(
            value: self::NEUTRAL_SCORE,
            weight: self::WEIGHT,
            reason: sprintf('Neutral TLD: .%s', $tld),
        ));
    }

    private function extractTld(string $host): string
    {
        $parts = explode('.', strtolower($host));
        return end($parts);
    }

    private function extractExtendedTld(string $host): string
    {
        $parts = explode('.', strtolower($host));
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
        }
        return end($parts);
    }
}
```

**Signal**: `tld_reputation`

**Test Cases**:

- `example.tk` → Signal 0.15, reason: "Suspicious TLD: .tk"
- `whitehouse.gov` → Signal 0.95, reason: "Trusted TLD: .gov"
- `example.com` → Signal 0.60, reason: "Neutral TLD: .com"

---

### 3. HttpsProcessor

**Purpose**: Score documents based on HTTPS usage.

**Priority**: 90

**Rationale**: In 2026, non-HTTPS sites are increasingly rare for legitimate content. HTTP-only indicates either very
old infrastructure, misconfiguration, or intentional avoidance of security measures.

```php
final readonly class HttpsProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.5;
    private const float HTTPS_SCORE = 0.80;
    private const float HTTP_SCORE = 0.35;

    public function priority(): int
    {
        return 90;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $url = $context->document->getUrl();
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === 'https') {
            return $context->withSignal('https', new Signal(
                value: self::HTTPS_SCORE,
                weight: self::WEIGHT,
                reason: 'HTTPS: Secure connection',
            ));
        }

        return $context->withSignal('https', new Signal(
            value: self::HTTP_SCORE,
            weight: self::WEIGHT,
            reason: 'HTTP only: No secure connection (unusual in 2026)',
        ));
    }
}
```

**Signal**: `https`

**Test Cases**:

- `https://example.com` → Signal 0.80
- `http://example.com` → Signal 0.35

---

### 4. AdblockDomainProcessor

**Purpose**: Score documents based on domain presence in community-maintained adblock/malware lists.

**Priority**: 88

**Rationale**: Community adblock lists (StevenBlack/hosts, EasyList, etc.) aggregate thousands of known ad,
tracking, malware, and low-quality content domains. While primarily designed for blocking ads, these lists also
capture domains associated with content farms, click-bait, and deceptive practices.

**Data Sources**:

| List              | Focus                  | Update Frequency |
| ----------------- | ---------------------- | ---------------- |
| StevenBlack/hosts | Ads, malware, fakenews | Daily            |
| EasyList          | Advertising domains    | Weekly           |
| AdGuard Base      | Ads, tracking          | Daily            |
| MVPS HOSTS        | Parasitic domains      | Monthly          |

**Implementation**: Pre-load domain sets into memory (HashSet) for O(1) lookup. Update via scheduled task.

```php
final readonly class AdblockDomainProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.8;
    private const float CLEAN_SCORE = 0.85;
    private const float FLAGGED_SCORE = 0.25;

    public function __construct(
        private AdblockDomainListProvider $listProvider,
    ) {}

    public function priority(): int
    {
        return 88;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $url = $context->document->getUrl();
        $domain = $this->extractDomain($url);

        $listMatch = $this->listProvider->findMatch($domain);

        if ($listMatch !== null) {
            return $context->withSignal('adblock_domain', new Signal(
                value: self::FLAGGED_SCORE,
                weight: self::WEIGHT,
                reason: sprintf(
                    'Domain %s found in adblock list: %s',
                    $domain,
                    $listMatch->listName
                ),
            ));
        }

        return $context->withSignal('adblock_domain', new Signal(
            value: self::CLEAN_SCORE,
            weight: self::WEIGHT,
            reason: 'Domain not in adblock lists',
        ));
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return strtolower(preg_replace('/^www\./i', '', $host));
    }
}
```

**Supporting Infrastructure**:

```php
interface AdblockDomainListProvider
{
    /**
     * Check if domain is in any loaded adblock list.
     * Returns match details or null if clean.
     */
    public function findMatch(string $domain): ?AdblockMatch;

    /**
     * Refresh lists from sources.
     */
    public function refresh(): void;
}

final readonly class AdblockMatch
{
    public function __construct(
        public string $domain,
        public string $listName,
        public string $category, // 'ads', 'malware', 'fakenews', 'tracking'
    ) {}
}
```

**Signal**: `adblock_domain`

**Test Cases**:

- `doubleclick.net` → Signal 0.25, "Domain found in adblock list: EasyList (ads)"
- `lemonde.fr` → Signal 0.85, "Domain not in adblock lists"
- `tracking.example.com` (if in list) → Signal 0.25

**Weight Rationale**: 0.8 (moderate) - adblock lists are useful signals but can have false positives for legitimate
sites that serve ads. Combined with other signals for balanced scoring.

---

## P1 Processors: Content Heuristics

### 5. WordCountProcessor

**Purpose**: Score documents based on content length, with source-type awareness.

**Priority**: 70

**Rationale**: Very short documents (<50 words) are typically navigation pages, error pages, or stubs. Very long
documents (>10000 words) may be content dumps or auto-generated.

**Social media exception**: Posts from social networks (X/Twitter, LinkedIn, Facebook, Instagram, etc.) are inherently
short-form content. A tweet is limited to 280 characters (~40-50 words), a LinkedIn post to ~3000 characters. Applying
standard word count thresholds to social media would systematically penalize legitimate, high-value content. The
processor detects social media sources via URL domain and applies adapted thresholds.

```php
final readonly class WordCountProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 1.5;

    // Standard thresholds (articles, blog posts, reports)
    private const int MIN_WORDS = 50;
    private const int OPTIMAL_MIN = 200;
    private const int OPTIMAL_MAX = 5000;
    private const int MAX_WORDS = 15000;

    // Social media thresholds
    private const int SOCIAL_MIN_WORDS = 5;
    private const int SOCIAL_OPTIMAL_MIN = 15;
    private const int SOCIAL_OPTIMAL_MAX = 500;

    // Illustrative — in practice, source type should come from
    // the Document metadata (e.g. sourceType: 'social_media')
    // populated by the collection provider, not from URL matching.
    private const array SOCIAL_MEDIA_DOMAINS = [
        'twitter.com',
        'x.com',
        'linkedin.com',
        'facebook.com',
        'instagram.com',
        'threads.net',
        'mastodon.social',
        'bsky.app',
        'reddit.com',
        't.me',
    ];

    public function priority(): int
    {
        return 70;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision();
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $wordCount = $context->document->getWordCount();

        if ($this->isSocialMedia($context->document->getUrl())) {
            return $this->scoreSocialMedia($context, $wordCount);
        }

        return $this->scoreStandard($context, $wordCount);
    }

    private function isSocialMedia(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $host = preg_replace('/^www\./i', '', $host);

        foreach (self::SOCIAL_MEDIA_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    private function scoreSocialMedia(ProcessingContext $context, int $wordCount): ProcessingContext
    {
        if ($wordCount < self::SOCIAL_MIN_WORDS) {
            return $context->withSignal('word_count', new Signal(
                value: 0.20,
                weight: self::WEIGHT,
                reason: sprintf('Social media post too short: %d words', $wordCount),
            ));
        }

        if ($wordCount < self::SOCIAL_OPTIMAL_MIN) {
            $score = 0.4 + (($wordCount - self::SOCIAL_MIN_WORDS) / (self::SOCIAL_OPTIMAL_MIN - self::SOCIAL_MIN_WORDS)) * 0.35;
            return $context->withSignal('word_count', new Signal(
                value: $score,
                weight: self::WEIGHT,
                reason: sprintf('Short social media post: %d words', $wordCount),
            ));
        }

        if ($wordCount <= self::SOCIAL_OPTIMAL_MAX) {
            return $context->withSignal('word_count', new Signal(
                value: 0.80,
                weight: self::WEIGHT,
                reason: sprintf('Social media post: %d words', $wordCount),
            ));
        }

        // Very long for social media — likely a thread or article link
        return $context->withSignal('word_count', new Signal(
            value: 0.70,
            weight: self::WEIGHT,
            reason: sprintf('Long social media content: %d words (thread or article)', $wordCount),
        ));
    }

    private function scoreStandard(ProcessingContext $context, int $wordCount): ProcessingContext
    {
        if ($wordCount < self::MIN_WORDS) {
            return $context->withSignal('word_count', new Signal(
                value: 0.15,
                weight: self::WEIGHT,
                reason: sprintf('Too short: %d words (min: %d)', $wordCount, self::MIN_WORDS),
            ));
        }

        if ($wordCount < self::OPTIMAL_MIN) {
            $score = 0.3 + (($wordCount - self::MIN_WORDS) / (self::OPTIMAL_MIN - self::MIN_WORDS)) * 0.4;
            return $context->withSignal('word_count', new Signal(
                value: $score,
                weight: self::WEIGHT,
                reason: sprintf('Short content: %d words', $wordCount),
            ));
        }

        if ($wordCount <= self::OPTIMAL_MAX) {
            return $context->withSignal('word_count', new Signal(
                value: 0.85,
                weight: self::WEIGHT,
                reason: sprintf('Optimal length: %d words', $wordCount),
            ));
        }

        if ($wordCount <= self::MAX_WORDS) {
            $score = 0.85 - (($wordCount - self::OPTIMAL_MAX) / (self::MAX_WORDS - self::OPTIMAL_MAX)) * 0.35;
            return $context->withSignal('word_count', new Signal(
                value: $score,
                weight: self::WEIGHT,
                reason: sprintf('Long content: %d words', $wordCount),
            ));
        }

        return $context->withSignal('word_count', new Signal(
            value: 0.40,
            weight: self::WEIGHT,
            reason: sprintf('Excessive length: %d words (possible content dump)', $wordCount),
        ));
    }
}
```

**Signal**: `word_count`

**Test Cases (standard content)**:

- 30 words → Signal 0.15, "Too short"
- 150 words → Signal ~0.55, "Short content"
- 1000 words → Signal 0.85, "Optimal length"
- 12000 words → Signal ~0.55, "Long content"

**Test Cases (social media)**:

- Tweet 3 words → Signal 0.20, "Social media post too short"
- Tweet 40 words → Signal 0.80, "Social media post"
- LinkedIn post 200 words → Signal 0.80, "Social media post"
- Twitter thread 600 words → Signal 0.70, "Long social media content"

---

### 6. ContentRatioProcessor

**Purpose**: Score based on text-to-HTML ratio (content density).

**Priority**: 68

**Rationale**: Pages with low content ratio (< 15% text vs HTML) are typically navigation pages, image galleries, or
ad-heavy pages with little actual content.

```php
final readonly class ContentRatioProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 1.0;
    private const float MIN_RATIO = 0.10;
    private const float OPTIMAL_RATIO = 0.35;

    public function priority(): int
    {
        return 68;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getRawHtml() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $rawHtml = $context->document->getRawHtml();
        $textContent = $context->document->getContent();

        $htmlLength = strlen($rawHtml);
        $textLength = strlen($textContent);

        if ($htmlLength === 0) {
            return $context->withSignal('content_ratio', new Signal(
                value: 0.3,
                weight: self::WEIGHT,
                reason: 'No HTML content to analyze',
            ));
        }

        $ratio = $textLength / $htmlLength;

        if ($ratio < self::MIN_RATIO) {
            return $context->withSignal('content_ratio', new Signal(
                value: 0.20,
                weight: self::WEIGHT,
                reason: sprintf(
                    'Low content ratio: %.1f%% (navigation/ad-heavy page)',
                    $ratio * 100
                ),
            ));
        }

        if ($ratio < self::OPTIMAL_RATIO) {
            $score = 0.35 + (($ratio - self::MIN_RATIO) / (self::OPTIMAL_RATIO - self::MIN_RATIO)) * 0.45;
            return $context->withSignal('content_ratio', new Signal(
                value: $score,
                weight: self::WEIGHT,
                reason: sprintf('Moderate content ratio: %.1f%%', $ratio * 100),
            ));
        }

        return $context->withSignal('content_ratio', new Signal(
            value: 0.85,
            weight: self::WEIGHT,
            reason: sprintf('Good content ratio: %.1f%%', $ratio * 100),
        ));
    }
}
```

**Signal**: `content_ratio`

**Test Cases**:

- 5% ratio → Signal 0.20, "Low content ratio: navigation/ad-heavy page"
- 20% ratio → Signal ~0.55, "Moderate content ratio"
- 40% ratio → Signal 0.85, "Good content ratio"

---

### 7. UrlPatternProcessor

**Purpose**: Score based on URL structure patterns.

**Priority**: 85

**Rationale**: URL patterns reveal content type. Deep URLs, keyword stuffing, and certain path patterns indicate
navigation pages, SEO spam, or non-article content.

```php
final readonly class UrlPatternProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.8;

    // URL path patterns that indicate low-value content
    private const array NEGATIVE_PATTERNS = [
        '/\/(login|signin|auth|logout|register)\b/i' => ['score' => 0.10, 'reason' => 'Authentication page'],
        '/\/(cart|checkout|basket|payment)\b/i' => ['score' => 0.15, 'reason' => 'E-commerce transaction page'],
        '/\/(privacy|terms|legal|cookie|gdpr|cgu|cgv)\b/i' => ['score' => 0.25, 'reason' => 'Legal/policy page'],
        '/\/(tag|category|archive)\/[^\/]+$/i' => ['score' => 0.30, 'reason' => 'Category/tag listing page'],
        '/\/page\/\d+/i' => ['score' => 0.25, 'reason' => 'Pagination page'],
        '/\/(search|recherche)\b/i' => ['score' => 0.20, 'reason' => 'Search results page'],
        '/\/(contact|about|a-propos|qui-sommes-nous)\b/i' => ['score' => 0.40, 'reason' => 'Corporate info page'],
    ];

    // Patterns indicating potential SEO spam
    private const string KEYWORD_STUFFING_PATTERN = '/(-[a-z]+){6,}/i';

    public function priority(): int
    {
        return 85;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $url = $context->document->getUrl();
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        // Check negative patterns
        foreach (self::NEGATIVE_PATTERNS as $pattern => $config) {
            if (preg_match($pattern, $path)) {
                return $context->withSignal('url_pattern', new Signal(
                    value: $config['score'],
                    weight: self::WEIGHT,
                    reason: $config['reason'],
                ));
            }
        }

        // Check URL depth (excessive depth = likely navigation)
        $depth = substr_count(trim($path, '/'), '/');
        if ($depth > 5) {
            return $context->withSignal('url_pattern', new Signal(
                value: 0.40,
                weight: self::WEIGHT,
                reason: sprintf('Deep URL structure: %d levels', $depth + 1),
            ));
        }

        // Check keyword stuffing in slug
        if (preg_match(self::KEYWORD_STUFFING_PATTERN, $path)) {
            return $context->withSignal('url_pattern', new Signal(
                value: 0.35,
                weight: self::WEIGHT,
                reason: 'Potential keyword stuffing in URL slug',
            ));
        }

        // Default: neutral URL structure
        return $context->withSignal('url_pattern', new Signal(
            value: 0.70,
            weight: self::WEIGHT,
            reason: 'Standard URL structure',
        ));
    }
}
```

**Signal**: `url_pattern`

**Test Cases**:

- `/login` → Signal 0.10, "Authentication page"
- `/blog/2024/article-title` → Signal 0.70, "Standard URL structure"
- `/a/b/c/d/e/f/g/page` → Signal 0.40, "Deep URL structure: 8 levels"
- `/best-cheap-amazing-incredible-fantastic-deals` → Signal 0.35, "Keyword stuffing"

---

### 8. PublicationDateProcessor

**Purpose**: Score based on presence and recency of publication date.

**Priority**: 65

**Rationale**: Quality content typically has identifiable publication dates. Absence of date often indicates evergreen
SEO content, navigation pages, or auto-generated content.

```php
final readonly class PublicationDateProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.7;

    public function priority(): int
    {
        return 65;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision();
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $publishedAt = $context->document->getPublishedAt();

        if ($publishedAt === null) {
            return $context->withSignal('publication_date', new Signal(
                value: 0.40,
                weight: self::WEIGHT,
                reason: 'No publication date detected',
            ));
        }

        $now = new \DateTimeImmutable();
        $age = $now->diff($publishedAt);
        $daysSincePublication = $age->days;

        // Future date = suspicious
        if ($publishedAt > $now) {
            return $context->withSignal('publication_date', new Signal(
                value: 0.25,
                weight: self::WEIGHT,
                reason: 'Future publication date (suspicious)',
            ));
        }

        // Very old content (> 5 years)
        if ($daysSincePublication > 1825) {
            return $context->withSignal('publication_date', new Signal(
                value: 0.50,
                weight: self::WEIGHT,
                reason: sprintf('Old content: %d years ago', intdiv($daysSincePublication, 365)),
            ));
        }

        // Recent content with date = good signal
        return $context->withSignal('publication_date', new Signal(
            value: 0.80,
            weight: self::WEIGHT,
            reason: sprintf('Published: %s', $publishedAt->format('Y-m-d')),
        ));
    }
}
```

**Signal**: `publication_date`

**Test Cases**:

- No date → Signal 0.40, "No publication date detected"
- Future date → Signal 0.25, "Future publication date (suspicious)"
- 2 months ago → Signal 0.80, "Published: 2025-11-11"
- 7 years ago → Signal 0.50, "Old content: 7 years ago"

---

## Signal Weight Summary

### By Category

**`infrastructure_trust`** — Is the source technically trustworthy?

| Signal                 | Weight | Priority | Primary Impact                       |
| ---------------------- | ------ | -------- | ------------------------------------ |
| `watchfile_override`\* | 2.0    | 100      | Client trust lists (or early reject) |
| `tld_reputation`       | 1.0    | 95       | Source infrastructure trust          |
| `adblock_domain`       | 0.8    | 88       | Known ad/malware domain detection    |
| `https`                | 0.5    | 90       | Basic security baseline              |

\* `watchfile_override` triggers early reject for blocked domains; for trusted domains, signal is injected into this category.

**`content_quality`** — Is the content substantial?

| Signal          | Weight | Priority | Primary Impact                 |
| --------------- | ------ | -------- | ------------------------------ |
| `word_count`    | 1.5    | 70       | Content substance              |
| `content_ratio` | 1.0    | 68       | Content density vs boilerplate |

**`metadata`** — Does the metadata look legitimate?

| Signal             | Weight | Priority | Primary Impact                        |
| ------------------ | ------ | -------- | ------------------------------------- |
| `url_pattern`      | 0.8    | 85       | URL structure signals                 |
| `publication_date` | 0.7    | 65       | Content freshness/editorial standards |

### Scoring Behavior

The final score = **min(infrastructure_trust, content_quality, metadata)**. A document must score well on
**every dimension** to be accepted. A suspicious TLD (infrastructure 0.53) cannot be saved by good content (0.79).

**Note**: Weights are relative within each category. Adding a new processor to a category refines that category's
score without affecting other categories.

---

## Deferred Processors

The following processors were considered but deferred to Phase 2:

| Processor                     | Reason for deferral                                             |
| ----------------------------- | --------------------------------------------------------------- |
| **RedirectCountProcessor**    | Redirect count not reliably available from collection providers |
| **DomainAgeProcessor**        | Requires WHOIS/RDAP API integration                             |
| **SourceReputationProcessor** | Requires Kagi/MBFC data sync infrastructure                     |
| **DeduplicationProcessor**    | Requires fingerprint storage (Redis/ES)                         |

---

## Directory Structure

```
api/src/
├── Domain/
│   └── DocumentQuality/
│       ├── DocumentProcessorInterface.php
│       └── ...
│
└── Infrastructure/
    └── DocumentQuality/
        └── Processor/
            └── Scoring/
                ├── WatchFileOverrideProcessor.php
                ├── TldReputationProcessor.php
                ├── HttpsProcessor.php
                ├── AdblockDomainProcessor.php
                ├── WordCountProcessor.php
                ├── ContentRatioProcessor.php
                ├── UrlPatternProcessor.php
                └── PublicationDateProcessor.php
```

---

## Symfony Service Configuration

```yaml
# config/services.yaml
services:
    # Auto-register all scoring processors
    App\Infrastructure\DocumentQuality\Processor\Scoring\:
        resource: '../src/Infrastructure/DocumentQuality/Processor/Scoring/'
        tags: ['app.document_processor']
```

---

## Consequences

### Positive

1. **Immediate impact**: P0 processors eliminate obvious spam (suspicious TLDs, blocked domains)
2. **Zero external dependencies**: All Phase 1 processors use data available at collection time
3. **Explainable decisions**: Every signal has a human-readable reason
4. **Configurable**: WatchFile override respects client-specific requirements
5. **Extensible**: Category-based scoring allows adding Phase 2 processors without cross-category rebalancing
6. **No compensation**: A weak category (e.g. suspicious infrastructure) cannot be hidden by strong content

### Negative

1. **False positives risk**: Legitimate content on `.tk` domains will be penalized
2. **Static lists**: TLD reputation list requires manual maintenance
3. **Missing signals**: No semantic/relevance analysis yet (deferred to Phase 2 processors)

### Risks and Mitigations

| Risk                                    | Mitigation                                           |
| --------------------------------------- | ---------------------------------------------------- |
| TLD list becomes outdated               | Quarterly review + WatchFile override for exceptions |
| URL patterns too aggressive             | Scoring (not blocking), client can adjust thresholds |
| Content ratio unreliable for some sites | Standard weight (1.0), combined with other signals   |

---

## Implementation Plan

| Step | Description                            | Effort |
| ---- | -------------------------------------- | ------ |
| 1    | Implement P0 processors (4 processors) | 2 days |
| 2    | Implement P1 processors (4 processors) | 2 days |
| 3    | Unit tests for all processors          | 2 days |
| 4    | Integration tests with pipeline        | 1 day  |
| 5    | Load testing with sample dataset       | 1 day  |

---

## Related ADRs

- **ADR-2026-003**: Document Processing Pipeline Architecture
- **ADR-2026-005**: Document Quality Scoring Processors Phase 2 (Source reputation, Domain age, Deduplication)
- **Future**: Legacy validation data integration for source trust scoring
