# ADR-0027: Document Quality Scoring Processors Phase 2

> Migrated from basil ADR-2026-005

## Status

**Status:** Accepted

**Date:** 2026-01-11

**Decision Makers:** Frédéric Fayard-Le Barzic

**Tags:** backend, quality-scoring, document-processing, pipeline, processors, external-apis

---

## Context

ADR-0026 established Phase 1 processors using only data available at collection time. This ADR defines **Phase 2
processors** that require external data sources, API integrations, or pre-computed infrastructure.

### Phase 2 Goals

1. **Source reputation**: Leverage external credibility databases (Kagi, MBFC, legacy validation)
2. **Domain intelligence**: WHOIS/RDAP data for domain age and registration patterns
3. **Duplicate detection**: Integration with deduplication system (see ADR-0028)
4. **Social signals**: Optional integration with social proof indicators

### Dependencies

| Processor                 | External Dependency                    | Infrastructure Required        |
| ------------------------- | -------------------------------------- | ------------------------------ |
| SourceReputationProcessor | Kagi API, MBFC dataset, legacy DB sync | Data sync service, cache layer |
| DomainAgeProcessor        | WHOIS/RDAP APIs                        | Rate-limited client, cache     |
| DuplicateProcessor        | Fingerprint storage (ADR-0028)         | Redis/Elasticsearch            |
| SocialProofProcessor      | Social APIs (optional)                 | API clients, rate limiting     |

## Decision

Implement **4 processors** in Phase 2, with infrastructure dependencies managed through dedicated services.

---

## P2 Processors: External Data Integration

### 1. SourceReputationProcessor

**Purpose**: Score documents based on source credibility from multiple reputation databases.

**Priority**: 92 (high, runs early after P0 processors)

**Data Sources**:

| Source                    | Data Type               | Update Frequency | Coverage         |
| ------------------------- | ----------------------- | ---------------- | ---------------- |
| **Kagi Web Index**        | Domain quality score    | Real-time API    | Broad            |
| **Media Bias/Fact Check** | Bias rating, factuality | Weekly sync      | News sources     |
| **Legacy Validation**     | Historical trust data   | Migration sync   | Existing sources |
| **OpenPageRank**          | PageRank-style score    | Monthly sync     | Broad            |

**Aggregation Strategy**: Weighted combination of available signals, with fallback to neutral score when no data exists.

```php
final readonly class SourceReputationProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 1.5;
    private const float NO_DATA_SCORE = 0.55;

    public function __construct(
        private SourceReputationProvider $reputationProvider,
    ) {}

    public function priority(): int
    {
        return 92;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $domain = $this->extractDomain($context->document->getUrl());

        $reputation = $this->reputationProvider->getReputation($domain);

        if ($reputation === null) {
            return $context->withSignal('source_reputation', new Signal(
                value: self::NO_DATA_SCORE,
                weight: self::WEIGHT * 0.5, // Reduced weight when no data
                reason: 'No reputation data available for domain',
            ));
        }

        return $context->withSignal('source_reputation', new Signal(
            value: $reputation->score,
            weight: self::WEIGHT,
            reason: $this->buildReason($reputation),
        ));
    }

    private function buildReason(SourceReputation $reputation): string
    {
        $parts = [];

        if ($reputation->kagiScore !== null) {
            $parts[] = sprintf('Kagi: %.0f%%', $reputation->kagiScore * 100);
        }

        if ($reputation->mbfcRating !== null) {
            $parts[] = sprintf('MBFC: %s', $reputation->mbfcRating->label());
        }

        if ($reputation->legacyTrust !== null) {
            $parts[] = sprintf('Legacy: %.0f%%', $reputation->legacyTrust * 100);
        }

        return 'Source reputation: ' . implode(', ', $parts);
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
interface SourceReputationProvider
{
    /**
     * Get aggregated reputation for a domain.
     * Returns null if no reputation data exists.
     */
    public function getReputation(string $domain): ?SourceReputation;
}

final readonly class SourceReputation
{
    public function __construct(
        public string $domain,
        public float $score,              // Aggregated 0.0 - 1.0
        public ?float $kagiScore,         // Kagi quality score
        public ?MbfcRating $mbfcRating,   // Media Bias/Fact Check
        public ?float $legacyTrust,       // From legacy validation system
        public ?float $pageRank,          // OpenPageRank score
        public \DateTimeImmutable $cachedAt,
    ) {}
}

enum MbfcRating: string
{
    case VERY_HIGH = 'very_high';       // Score: 0.95
    case HIGH = 'high';                  // Score: 0.85
    case MOSTLY_FACTUAL = 'mostly';      // Score: 0.70
    case MIXED = 'mixed';                // Score: 0.50
    case LOW = 'low';                    // Score: 0.30
    case VERY_LOW = 'very_low';          // Score: 0.15
    case SATIRE = 'satire';              // Score: 0.40 (not factual but legitimate)

    public function score(): float
    {
        return match ($this) {
            self::VERY_HIGH => 0.95,
            self::HIGH => 0.85,
            self::MOSTLY_FACTUAL => 0.70,
            self::MIXED => 0.50,
            self::LOW => 0.30,
            self::VERY_LOW => 0.15,
            self::SATIRE => 0.40,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::VERY_HIGH => 'Very High Factuality',
            self::HIGH => 'High Factuality',
            self::MOSTLY_FACTUAL => 'Mostly Factual',
            self::MIXED => 'Mixed Factuality',
            self::LOW => 'Low Factuality',
            self::VERY_LOW => 'Very Low Factuality',
            self::SATIRE => 'Satire/Parody',
        };
    }
}
```

**Score Aggregation Formula**:

```php
private function aggregateScore(
    ?float $kagi,
    ?MbfcRating $mbfc,
    ?float $legacy,
    ?float $pageRank,
): float {
    $scores = [];
    $weights = [];

    if ($kagi !== null) {
        $scores[] = $kagi;
        $weights[] = 1.5; // Kagi has good coverage and quality
    }

    if ($mbfc !== null) {
        $scores[] = $mbfc->score();
        $weights[] = 2.0; // MBFC is authoritative for news
    }

    if ($legacy !== null) {
        $scores[] = $legacy;
        $weights[] = 1.0; // Legacy data from existing system
    }

    if ($pageRank !== null) {
        $scores[] = $this->normalizePageRank($pageRank);
        $weights[] = 0.5; // PageRank is popularity, not quality
    }

    if (empty($scores)) {
        return self::NO_DATA_SCORE;
    }

    $totalWeight = array_sum($weights);
    $weightedSum = 0;

    foreach ($scores as $i => $score) {
        $weightedSum += $score * $weights[$i];
    }

    return $weightedSum / $totalWeight;
}
```

**Signal**: `source_reputation`

**Weight**: 1.5 (important - external validation of source quality)

**Test Cases**:

- `lemonde.fr` (MBFC: High) → Signal ~0.85
- `infowars.com` (MBFC: Very Low) → Signal ~0.15
- `unknown-blog.xyz` (no data) → Signal 0.55, reduced weight

---

### 2. DomainAgeProcessor

**Purpose**: Score documents based on domain registration age and patterns.

**Priority**: 75

**Rationale**: Newly registered domains are frequently used for spam, phishing, and content farms. Established domains
have implicit trust. However, domain age alone is not definitive—legitimate new sites exist.

**Data Source**: WHOIS/RDAP APIs with aggressive caching (domain age doesn't change).

```php
final readonly class DomainAgeProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.8;

    // Age thresholds in days
    private const int VERY_NEW_DAYS = 30;        // < 1 month
    private const int NEW_DAYS = 180;            // < 6 months
    private const int ESTABLISHED_DAYS = 730;   // > 2 years
    private const int VETERAN_DAYS = 3650;      // > 10 years

    public function __construct(
        private DomainAgeProvider $ageProvider,
    ) {}

    public function priority(): int
    {
        return 75;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $domain = $this->extractRootDomain($context->document->getUrl());

        $domainInfo = $this->ageProvider->getDomainInfo($domain);

        if ($domainInfo === null) {
            return $context->withSignal('domain_age', new Signal(
                value: 0.50,
                weight: self::WEIGHT * 0.5,
                reason: 'Unable to retrieve domain registration data',
            ));
        }

        $ageInDays = $domainInfo->ageInDays();

        if ($ageInDays < self::VERY_NEW_DAYS) {
            return $context->withSignal('domain_age', new Signal(
                value: 0.20,
                weight: self::WEIGHT,
                reason: sprintf(
                    'Very new domain: registered %d days ago (suspicious)',
                    $ageInDays
                ),
            ));
        }

        if ($ageInDays < self::NEW_DAYS) {
            $score = 0.30 + (($ageInDays - self::VERY_NEW_DAYS) /
                (self::NEW_DAYS - self::VERY_NEW_DAYS)) * 0.25;
            return $context->withSignal('domain_age', new Signal(
                value: $score,
                weight: self::WEIGHT,
                reason: sprintf('New domain: registered %d days ago', $ageInDays),
            ));
        }

        if ($ageInDays < self::ESTABLISHED_DAYS) {
            return $context->withSignal('domain_age', new Signal(
                value: 0.65,
                weight: self::WEIGHT,
                reason: sprintf(
                    'Moderate age: registered %.1f years ago',
                    $ageInDays / 365
                ),
            ));
        }

        if ($ageInDays < self::VETERAN_DAYS) {
            return $context->withSignal('domain_age', new Signal(
                value: 0.80,
                weight: self::WEIGHT,
                reason: sprintf(
                    'Established domain: registered %.1f years ago',
                    $ageInDays / 365
                ),
            ));
        }

        return $context->withSignal('domain_age', new Signal(
            value: 0.90,
            weight: self::WEIGHT,
            reason: sprintf(
                'Veteran domain: registered %.0f years ago',
                $ageInDays / 365
            ),
        ));
    }

    private function extractRootDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $host = strtolower(preg_replace('/^www\./i', '', $host));

        // Extract root domain (e.g., subdomain.example.com -> example.com)
        // This is simplified; production should use Public Suffix List
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            return implode('.', array_slice($parts, -2));
        }
        return $host;
    }
}
```

**Supporting Infrastructure**:

```php
interface DomainAgeProvider
{
    /**
     * Get domain registration information.
     * Returns null if lookup fails or domain not found.
     */
    public function getDomainInfo(string $domain): ?DomainInfo;
}

final readonly class DomainInfo
{
    public function __construct(
        public string $domain,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $expiresAt,
        public ?string $registrar,
        public \DateTimeImmutable $cachedAt,
    ) {}

    public function ageInDays(): int
    {
        $now = new \DateTimeImmutable();
        return $now->diff($this->createdAt)->days;
    }
}
```

**Caching Strategy**:

- Cache TTL: 30 days (domain age changes slowly)
- Negative cache: 7 days (for lookup failures)
- Rate limiting: 10 requests/second to RDAP endpoints

**Signal**: `domain_age`

**Weight**: 0.8 (moderate - useful signal but not definitive)

**Test Cases**:

- Domain registered 15 days ago → Signal 0.20, "Very new domain (suspicious)"
- Domain registered 3 months ago → Signal ~0.40, "New domain"
- Domain registered 5 years ago → Signal 0.80, "Established domain"
- Domain registered 15 years ago → Signal 0.90, "Veteran domain"

---

### 3. DuplicateProcessor

**Purpose**: Detect and score duplicate/near-duplicate documents.

**Priority**: 60 (runs after content filters, needs document content)

**Rationale**: Duplicate content indicates scraping, syndication, or content farming. Near-duplicates (>85% similarity)
should be flagged. Exact duplicates should be early-rejected.

**Integration**: Uses deduplication infrastructure from ADR-0028.

```php
final readonly class DuplicateProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 1.2;
    private const float EXACT_DUPLICATE_THRESHOLD = 0.98;
    private const float NEAR_DUPLICATE_THRESHOLD = 0.85;

    public function __construct(
        private DocumentFingerprintService $fingerprintService,
        private DuplicateDetector $duplicateDetector,
    ) {}

    public function priority(): int
    {
        return 60;
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision()
            && $context->document->getContent() !== null
            && strlen($context->document->getContent()) > 100;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $document = $context->document;
        $watchFile = $context->watchFile;

        // Compute fingerprint for current document
        $fingerprint = $this->fingerprintService->compute($document);

        // Find similar documents in the same WatchFile
        $matches = $this->duplicateDetector->findSimilar(
            fingerprint: $fingerprint,
            watchFileId: $watchFile->getId(),
            threshold: self::NEAR_DUPLICATE_THRESHOLD,
            limit: 5,
        );

        if (empty($matches)) {
            return $context->withSignal('duplicate', new Signal(
                value: 0.90,
                weight: self::WEIGHT,
                reason: 'Unique content (no duplicates detected)',
            ));
        }

        $bestMatch = $matches[0];

        // Exact duplicate: early reject
        if ($bestMatch->similarity >= self::EXACT_DUPLICATE_THRESHOLD) {
            return $context->withEarlyDecision(
                QualityDecision::REJECTED,
                sprintf(
                    'Exact duplicate of document %s (%.1f%% similarity)',
                    $bestMatch->documentId,
                    $bestMatch->similarity * 100
                )
            );
        }

        // Near duplicate: low score but continue
        $score = 0.20 + (1 - $bestMatch->similarity) * 0.5;

        return $context->withSignal('duplicate', new Signal(
            value: $score,
            weight: self::WEIGHT,
            reason: sprintf(
                'Near-duplicate of document %s (%.1f%% similarity)',
                $bestMatch->documentId,
                $bestMatch->similarity * 100
            ),
        ));
    }
}
```

**Supporting Infrastructure** (from ADR-0028):

```php
interface DocumentFingerprintService
{
    public function compute(Document $document): DocumentFingerprint;
}

interface DuplicateDetector
{
    /**
     * Find documents similar to the given fingerprint.
     * Returns matches sorted by similarity (descending).
     */
    public function findSimilar(
        DocumentFingerprint $fingerprint,
        WatchFileId $watchFileId,
        float $threshold,
        int $limit,
    ): array;
}

final readonly class DuplicateMatch
{
    public function __construct(
        public string $documentId,
        public float $similarity,
        public \DateTimeImmutable $indexedAt,
    ) {}
}
```

**Signal**: `duplicate`

**Weight**: 1.2 (important - duplicates waste resources and indicate low quality)

**Test Cases**:

- Unique content → Signal 0.90, "Unique content"
- 99% similar to existing → Early reject, "Exact duplicate"
- 88% similar to existing → Signal ~0.30, "Near-duplicate"

---

### 4. SocialProofProcessor (Optional)

**Purpose**: Score documents based on social engagement signals.

**Priority**: 55

**Rationale**: Documents with significant social engagement (shares, citations) have implicit validation from human
attention. However, social signals can be manipulated, so this processor has low weight.

**Status**: Optional - implement only if social data becomes available.

```php
final readonly class SocialProofProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.5;

    public function __construct(
        private SocialSignalProvider $socialProvider,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function supports(ProcessingContext $context): bool
    {
        // Only run if social data integration is enabled
        return !$context->hasEarlyDecision()
            && $context->document->getUrl() !== null
            && $this->socialProvider->isEnabled();
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $url = $context->document->getUrl();

        $signals = $this->socialProvider->getSignals($url);

        if ($signals === null) {
            // No social data - skip this signal entirely
            return $context;
        }

        $score = $this->calculateScore($signals);

        return $context->withSignal('social_proof', new Signal(
            value: $score,
            weight: self::WEIGHT,
            reason: $this->buildReason($signals),
        ));
    }

    private function calculateScore(SocialSignals $signals): float
    {
        // Normalize engagement to 0-1 scale
        // High engagement = higher score, but capped to avoid manipulation
        $totalEngagement = $signals->shares + $signals->citations + $signals->comments;

        return match (true) {
            $totalEngagement >= 10000 => 0.90,
            $totalEngagement >= 1000 => 0.80,
            $totalEngagement >= 100 => 0.70,
            $totalEngagement >= 10 => 0.60,
            $totalEngagement >= 1 => 0.50,
            default => 0.40,
        };
    }

    private function buildReason(SocialSignals $signals): string
    {
        $parts = [];

        if ($signals->shares > 0) {
            $parts[] = sprintf('%d shares', $signals->shares);
        }
        if ($signals->citations > 0) {
            $parts[] = sprintf('%d citations', $signals->citations);
        }
        if ($signals->comments > 0) {
            $parts[] = sprintf('%d comments', $signals->comments);
        }

        return 'Social proof: ' . (empty($parts) ? 'No engagement' : implode(', ', $parts));
    }
}
```

**Signal**: `social_proof`

**Weight**: 0.5 (weak - easily manipulated, supplementary only)

---

## Signal Weight Summary (Phase 2 Processors)

Phase 2 processors add signals to existing categories (and introduce a new one):

**`infrastructure_trust`** (extends Phase 1):

| Signal       | Weight | Priority | External Dependency |
| ------------ | ------ | -------- | ------------------- |
| `domain_age` | 0.8    | 75       | WHOIS/RDAP API      |

**`content_quality`** (extends Phase 1):

| Signal      | Weight | Priority | External Dependency |
| ----------- | ------ | -------- | ------------------- |
| `duplicate` | 1.2    | 60       | Fingerprint storage |

**`source_credibility`** (new category):

| Signal              | Weight | Priority | External Dependency    |
| ------------------- | ------ | -------- | ---------------------- |
| `source_reputation` | 1.5    | 92       | Kagi API, MBFC sync    |
| `social_proof`      | 0.5    | 55       | Social APIs (optional) |

### Impact on Final Score

With Phase 2, the final score becomes: **min(infrastructure_trust, content_quality, metadata, source_credibility)**.

The new `source_credibility` category adds a fourth dimension. A document from an unknown source with no reputation
data will score ~0.55 in this category, which may pull down the final score. This is intentional — source credibility
is a meaningful quality dimension that should not be ignored.

---

## Infrastructure Requirements

### 1. Source Reputation Data Sync

```yaml
# Scheduled sync jobs
services:
  App\Infrastructure\DocumentQuality\Sync\MbfcSyncCommand:
    tags:
      - { name: 'scheduler.schedule', schedule: '0 3 * * 0' } # Weekly Sunday 3am

  App\Infrastructure\DocumentQuality\Sync\OpenPageRankSyncCommand:
    tags:
      - { name: 'scheduler.schedule', schedule: '0 4 1 * *' } # Monthly 1st at 4am
```

### 2. WHOIS/RDAP Client

```php
final readonly class RdapDomainAgeProvider implements DomainAgeProvider
{
    private const int RATE_LIMIT_PER_SECOND = 10;
    private const int CACHE_TTL_DAYS = 30;
    private const int NEGATIVE_CACHE_TTL_DAYS = 7;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private RateLimiterFactory $rateLimiter,
    ) {}

    public function getDomainInfo(string $domain): ?DomainInfo
    {
        $cacheKey = 'domain_age_' . md5($domain);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($domain) {
            $limiter = $this->rateLimiter->create('rdap_lookup');

            if (!$limiter->consume()->isAccepted()) {
                $item->expiresAfter(60); // Retry in 1 minute
                return null;
            }

            try {
                $rdapData = $this->queryRdap($domain);
                $item->expiresAfter(self::CACHE_TTL_DAYS * 86400);
                return $this->parseRdapResponse($domain, $rdapData);
            } catch (\Exception $e) {
                $item->expiresAfter(self::NEGATIVE_CACHE_TTL_DAYS * 86400);
                return null;
            }
        });
    }
}
```

### 3. Database Schema Additions

```sql
-- Source reputation cache
CREATE TABLE source_reputation_cache
(
    id           UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    domain       VARCHAR(255)             NOT NULL UNIQUE,
    score        DECIMAL(5, 4)            NOT NULL,
    kagi_score   DECIMAL(5, 4),
    mbfc_rating  VARCHAR(20),
    legacy_trust DECIMAL(5, 4),
    page_rank    DECIMAL(5, 4),
    cached_at    TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    expires_at   TIMESTAMP WITH TIME ZONE NOT NULL
);

CREATE INDEX idx_source_reputation_domain ON source_reputation_cache (domain);
CREATE INDEX idx_source_reputation_expires ON source_reputation_cache (expires_at);

-- Domain age cache
CREATE TABLE domain_age_cache
(
    id                UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    domain            VARCHAR(255)             NOT NULL UNIQUE,
    created_at        TIMESTAMP WITH TIME ZONE,
    updated_at        TIMESTAMP WITH TIME ZONE,
    expires_at_domain TIMESTAMP WITH TIME ZONE,
    registrar         VARCHAR(255),
    cached_at         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    cache_expires_at  TIMESTAMP WITH TIME ZONE NOT NULL
);

CREATE INDEX idx_domain_age_domain ON domain_age_cache (domain);
CREATE INDEX idx_domain_age_expires ON domain_age_cache (cache_expires_at);
```

---

## Directory Structure

```text
api/src/
├── Domain/
│   └── DocumentQuality/
│       ├── SourceReputationProviderInterface.php
│       ├── DomainAgeProviderInterface.php
│       ├── SourceReputation.php
│       ├── DomainInfo.php
│       └── MbfcRating.php
│
└── Infrastructure/
    └── DocumentQuality/
        ├── Processor/
        │   └── Scoring/
        │       ├── SourceReputationProcessor.php
        │       ├── DomainAgeProcessor.php
        │       ├── DuplicateProcessor.php
        │       └── SocialProofProcessor.php
        │
        ├── Provider/
        │   ├── CachedSourceReputationProvider.php
        │   ├── RdapDomainAgeProvider.php
        │   └── SocialSignalProvider.php
        │
        └── Sync/
            ├── MbfcSyncCommand.php
            └── OpenPageRankSyncCommand.php
```

---

## Options Considered

_Not documented in original ADR._

---

## Consequences

### Positive

1. **Rich reputation data**: MBFC and Kagi provide authoritative source quality signals
2. **Spam detection**: Domain age catches freshly registered spam domains
3. **Duplicate elimination**: Prevents content farming and syndicated spam
4. **Layered defense**: Phase 2 processors complement Phase 1 heuristics

### Negative

1. **External dependencies**: Kagi API, RDAP endpoints have availability/rate concerns
2. **Data freshness**: MBFC/PageRank data may be outdated for new sources
3. **Infrastructure cost**: Additional caching, sync jobs, API quotas
4. **Cold start**: New domains have no reputation data (fall back to neutral)

### Risks and Mitigations

| Risk                                | Mitigation                                                |
| ----------------------------------- | --------------------------------------------------------- |
| Kagi API rate limits                | Aggressive caching (7 days), batch lookups                |
| RDAP endpoint unreliability         | Multiple RDAP servers, 30-day cache, graceful degradation |
| MBFC coverage gaps                  | Fall back to other signals, reduced weight when missing   |
| Duplicate detection false positives | High threshold (85%), scoring instead of blocking         |

---

## Implementation Plan

| Step | Description                               | Effort | Dependencies      |
| ---- | ----------------------------------------- | ------ | ----------------- |
| 1    | MBFC dataset sync infrastructure          | 2 days | -                 |
| 2    | SourceReputationProcessor + provider      | 2 days | Step 1            |
| 3    | RDAP client + caching layer               | 2 days | -                 |
| 4    | DomainAgeProcessor implementation         | 1 day  | Step 3            |
| 5    | DuplicateProcessor (after ADR-0028)       | 1 day  | ADR-0028          |
| 6    | Integration tests with external mocks     | 2 days | Steps 1-5         |
| 7    | SocialProofProcessor (optional, deferred) | 1 day  | Social API access |

---

## Related ADRs

- **ADR-0025**: Document Processing Pipeline Architecture
- **ADR-0026**: Document Quality Scoring Processors Phase 1
- **ADR-0028**: Document Deduplication Strategy (fingerprinting infrastructure)
