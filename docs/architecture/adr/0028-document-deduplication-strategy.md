# ADR-0028: Document Deduplication Strategy

> Migrated from basil ADR-2026-006

## Status

**Status:** Accepted

**Date:** 2026-01-11

**Decision Makers:** Frédéric Fayard-Le Barzic

**Tags:** backend, deduplication, document-processing, fingerprinting, simhash, performance

---

## Context

Target collects documents from multiple sources (web crawlers, RSS, APIs) for the same WatchFile. This creates
significant duplicate and near-duplicate content:

### Current Problems

1. **Exact duplicates**: Same article syndicated across multiple news sites
2. **Near-duplicates**: Articles with minor variations (different ads, headers, timestamps)
3. **Content farms**: Sites that copy/paste content with minimal modifications
4. **Versioned content**: Same article updated over time (corrections, additions)

### Scale Requirements

- Target: 50M documents/year (~140k docs/day)
- Duplicate rate estimate: 15-25% of collected content
- Query latency requirement: <50ms for duplicate detection per document
- Storage budget: Fingerprint storage must be efficient

### Goals

1. **Detect exact duplicates**: 100% recall for identical content
2. **Detect near-duplicates**: >90% recall for >85% similar content
3. **Low latency**: Duplicate check during ingestion pipeline
4. **Scalable storage**: Sublinear storage growth with document count
5. **WatchFile isolation**: Duplicates detected within same WatchFile only

### Infrastructure Constraints

| Component     | Development       | Production    | Notes                                    |
| ------------- | ----------------- | ------------- | ---------------------------------------- |
| Search Engine | Elasticsearch 9.x | OpenSearch    | API compatible, minor syntax differences |
| Cache         | Valkey 8          | Valkey 8      | Cache only, no persistence configured    |
| Database      | PostgreSQL 17     | PostgreSQL 17 | Documents metadata, not content          |

**Important**: Valkey is currently used only as a volatile cache (no RDB/AOF persistence, no Sentinel). Fingerprint
storage must use a persistent backend.

---

## Glossary

This section explains the algorithms and concepts used in document deduplication. For deeper understanding, refer to
the Wikipedia articles:

- [N-gram](https://en.wikipedia.org/wiki/N-gram) — foundation for shingle extraction
- [Jaccard index](https://en.wikipedia.org/wiki/Jaccard_index) — similarity metric between shingle sets
- [SimHash](https://en.wikipedia.org/wiki/SimHash) — locality-sensitive hash for near-exact detection
- [MinHash](https://en.wikipedia.org/wiki/MinHash) — compact signatures estimating Jaccard similarity
- [Locality-Sensitive Hashing (LSH)](https://en.wikipedia.org/wiki/Locality-sensitive_hashing) — candidate search
  without N² comparisons
- [Hamming distance](https://en.wikipedia.org/wiki/Hamming_distance) — fast bitwise comparison for SimHash
- [SHA-2](https://en.wikipedia.org/wiki/SHA-2) — cryptographic hash for exact content matching

### N-grams

An **n-gram** is a contiguous sequence of `n` items from a text. Items can be characters or words.

**Character n-grams** (n=3, "trigrams"):

```text
Text: "hello"
3-grams: ["hel", "ell", "llo"]
```

**Word n-grams** (n=2, "bigrams"):

```text
Text: "the quick brown fox"
2-grams: ["the quick", "quick brown", "brown fox"]
```

N-grams capture local structure. Similar documents share many n-grams.

---

### Shingles (Word N-grams)

A **shingle** is a word-level n-gram used for document similarity. Typically, 3-5 words.

**Example** (k=3, 3-word shingles):

```text
Text: "the quick brown fox jumps"
Shingles: ["the quick brown", "quick brown fox", "brown fox jumps"]
```

**Why word shingles over character n-grams (for space-separated languages)?**

- More semantic meaning per unit
- Smaller set size (fewer shingles than character n-grams)
- Better for detecting paraphrased content

**Note**: For CJK languages (Chinese, Japanese, Korean) that don't use spaces, character n-grams are used instead.
See **Multilingual Support** section.

**Shingle set**: The unique set of all shingles in a document. Two documents with similar shingle sets are likely
similar in content.

---

### Jaccard Similarity

**Jaccard similarity** measures the overlap between two sets:

```text
J(A, B) = |A ∩ B| / |A ∪ B|
```

Where:

- `|A ∩ B|` = number of elements in both sets (intersection)
- `|A ∪ B|` = number of elements in either set (union)

**Example**:

```text
Document A shingles: {"the quick brown", "quick brown fox", "brown fox jumps"}
Document B shingles: {"the quick brown", "quick brown fox", "brown fox runs"}

Intersection: {"the quick brown", "quick brown fox"} → 2 elements
Union: {"the quick brown", "quick brown fox", "brown fox jumps", "brown fox runs"} → 4 elements

Jaccard = 2/4 = 0.50 (50% similar)
```

**Properties**:

- Range: 0.0 (no overlap) to 1.0 (identical sets)
- Symmetric: J(A,B) = J(B,A)
- Computing exact Jaccard requires comparing all shingles → O(n) per pair → expensive at scale

---

### MinHash (Minimum Hashing)

**MinHash** is a technique to estimate Jaccard similarity without comparing full shingle sets.

**Key insight**: If we apply a random hash function to all shingles and take the minimum hash value, the probability
that two documents have the same minimum equals their Jaccard similarity.

```text
P(min(h(A)) = min(h(B))) = J(A, B)
```

**Algorithm**:

1. Define `k` independent hash functions (typically k=128)
2. For each document, compute the minimum hash value for each function
3. The result is a **signature** of `k` values

**Signature comparison**:

```text
Document A signature: [42, 17, 89, 31, ...]  (128 values)
Document B signature: [42, 17, 56, 31, ...]  (128 values)

Matching positions: 3 out of 4 shown → estimate J(A,B) ≈ 0.75
```

**Accuracy**: With k=128 hash functions, the standard error is ~1/√128 ≈ 8.8%

**Space efficiency**: 128 × 4 bytes = 512 bytes per document (vs. potentially thousands of shingles)

---

### Locality-Sensitive Hashing (LSH)

**LSH** is a technique to find similar items without comparing all pairs. It hashes similar items to the same
"bucket" with high probability.

**The problem**: With 1M documents, comparing all pairs = 500 billion comparisons. Impossible.

**LSH solution**: Divide the MinHash signature into **bands**. Documents that match in ANY band become candidates.

**Banding technique**:

```text
Signature: 128 hash values
Bands: 32 bands × 4 rows each

Band 1: [h1, h2, h3, h4]     → hash to bucket
Band 2: [h5, h6, h7, h8]     → hash to bucket
...
Band 32: [h125, h126, h127, h128] → hash to bucket
```

**Probability of becoming candidates**:

For two documents with Jaccard similarity `s`:

- Probability of matching one band: `s^r` (where r = rows per band)
- Probability of matching at least one band: `1 - (1 - s^r)^b` (where b = bands)

With b=32 bands, r=4 rows:

| Jaccard | P(candidate) |
| ------- | ------------ |
| 0.50    | 18%          |
| 0.80    | 97%          |
| 0.90    | 99.97%       |

**Result**: High-similarity pairs almost always become candidates; low-similarity pairs rarely do.

---

### SimHash (Similarity Hash)

**SimHash** produces a fixed-size hash where similar documents have similar hashes (small Hamming distance).

**Algorithm**:

1. Initialize a vector V of `n` counters (typically n=64), all set to 0
2. For each shingle in the document:
   a. Compute a traditional hash (64 bits)
   b. For each bit position i:
   - If bit i is 1: V[i] += 1
   - If bit i is 0: V[i] -= 1
3. Final hash: bit i = 1 if V[i] > 0, else 0

**Example** (simplified with 8 bits):

```text
Shingle "the quick" → hash: 10110010
Shingle "quick brown" → hash: 10100011
Shingle "brown fox" → hash: 11110000

Counters after processing:
Position: 0  1  2  3  4  5  6  7
Values:   3  1  1  1 -1  1 -1  1

Final SimHash: 11110101
```

**Key property**: Similar documents (sharing many shingles) will have similar counter sums, producing hashes that
differ in few bits.

---

### Hamming Distance

**Hamming distance** counts the number of positions where two bit strings differ.

```text
Hash A: 11110101
Hash B: 11100101
         ^
Hamming distance = 1 (one bit differs)
```

**SimHash similarity**: For 64-bit SimHash, Hamming distance relates to similarity:

| Hamming Distance | Approximate Similarity |
| ---------------- | ---------------------- |
| 0                | 100% (identical)       |
| 1-3              | >95%                   |
| 4-6              | 90-95%                 |
| 7-10             | 85-90%                 |
| >10              | <85%                   |

**Advantage**: Hamming distance is extremely fast to compute (XOR + popcount CPU instruction).

---

### Algorithm Decision Matrix

#### Why Four Stages?

Each stage detects a specific class of duplicates that the previous stages **cannot** catch. The pipeline is ordered
from cheapest to most expensive, and **short-circuits** as soon as a match is found.

```text
Stage 0: Canonical URL ──► match? ──► STOP (duplicate found)
         │ no match
         ▼
Stage 1: SHA256 hash ────► match? ──► STOP (duplicate found)
         │ no match
         ▼
Stage 2: SimHash ────────► match? ──► STOP (near-exact found)
         │ no match
         ▼
Stage 3: MinHash + LSH ──► match? ──► Flag as near-duplicate
         │ no match
         ▼
         Unique document → index fingerprints
```

#### What Each Stage Catches (and Why It's Needed)

| Stage | Algorithm                   | Catches                                           | Example                                                                                                       | Why previous stages miss it                                                                                           |
| ----- | --------------------------- | ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| **0** | Canonical URL (keyword)     | Same article published on different URLs          | AFP wire article on lemonde.fr and lefigaro.fr, both with `<link rel="canonical">` pointing to the AFP source | URLs differ → can't match by URL; content may also differ (different site templates, ads) → hash differs too          |
| **1** | SHA256 (cryptographic hash) | Byte-identical content after normalization        | Same article scraped from two mirrors, or same page re-crawled at different times                             | No canonical tag present; URLs are different; content is identical but has no canonical signal                        |
| **2** | SimHash (Hamming distance)  | Content with minor differences (<5% changed)      | Same article with updated publication date, corrected typo, or different trailing boilerplate                 | SHA256 changes completely with even 1 bit difference (avalanche effect); SimHash is resilient to small changes        |
| **3** | MinHash + LSH (Jaccard)     | Content with moderate differences (5-15% changed) | Article partially rewritten by a content farm, or same press release with editorial additions                 | SimHash loses accuracy below ~90% similarity; MinHash estimates set overlap (Jaccard) which handles larger variations |

#### Algorithm Properties Comparison

| Property                | Canonical URL                         | SHA256                                | SimHash                                     | MinHash + LSH                             |
| ----------------------- | ------------------------------------- | ------------------------------------- | ------------------------------------------- | ----------------------------------------- |
| **What it compares**    | URL string                            | Full normalized content               | Document "fingerprint" (64 bits)            | Shingle set overlap                       |
| **Output size**         | N/A (stored in document)              | 256 bits (32 bytes)                   | 64 bits (8 bytes)                           | 512 bytes (128 × 4B) + 32 band hashes     |
| **Similarity range**    | Binary: match or not                  | Binary: match or not                  | Continuous: 0-64 bits Hamming               | Continuous: 0.0-1.0 Jaccard               |
| **Detection threshold** | Exact URL match                       | Exact content match                   | Hamming ≤ 3 (~95%+)                         | Jaccard ≥ 0.85                            |
| **Query cost**          | ~2ms (keyword lookup)                 | ~2ms (keyword lookup)                 | ~5ms (retrieve + XOR + popcount)            | ~50ms (LSH candidate search + verify)     |
| **Computation cost**    | Negligible (URL normalization)        | Fast (single hash pass)               | Medium (hash all shingles, accumulate bits) | Heavy (128 hash functions × all shingles) |
| **False positives**     | Very rare (URL collision)             | Near-zero (SHA256 collision = 2^-128) | Possible at Hamming = 3 boundary            | ~8.8% standard error (k=128)              |
| **False negatives**     | High (many pages lack canonical tags) | High (any change = different hash)    | Misses changes >10%                         | Misses changes >15%                       |
| **Sensitive to**        | Missing/wrong canonical tags          | Any character change                  | Small edits (tolerant up to ~5%)            | Moderate edits (tolerant up to ~15%)      |
| **Insensitive to**      | Content changes                       | N/A                                   | Word reordering within shingles             | Paragraph reordering, insertions          |

#### Key Insight: Why SHA256 Alone Is Not Enough

SHA256 is a **cryptographic hash** designed so that any change to the input produces a completely different output
(avalanche effect). This is desirable for security (tamper detection) but counterproductive for near-duplicate
detection:

```text
Original:     "The president announced new measures today"
SHA256:       a3f2c7...

Modified:     "The president announced new measures yesterday"   (1 word changed)
SHA256:       e91b4d...   ← completely different hash, 0% similarity signal
SimHash:      differs by 2 bits   ← correctly detects near-identity
MinHash:      Jaccard ≈ 0.92      ← correctly detects high overlap
```

#### Key Insight: Why SimHash Alone Is Not Enough

SimHash compresses the entire document into a single 64-bit fingerprint. This works well for detecting near-identical
documents, but its resolution degrades as changes grow:

```text
Original article (500 words)
Same article + 50 words added by editor (10% change)

SimHash Hamming distance: 5-7 bits ← ambiguous zone, may miss
MinHash Jaccard: 0.88             ← clearly detected as near-duplicate
```

SimHash's strength is **speed** (single XOR + popcount), but it sacrifices **granularity**. MinHash retains information
about individual shingle overlaps, making it more accurate for moderate differences.

#### Key Insight: Why MinHash Without LSH Would Be Too Slow

MinHash alone gives accurate Jaccard estimation, but comparing a new document against **all** existing documents
is O(N) — unacceptable at scale:

```text
50M documents × 128 integers × comparison = infeasible

With LSH (32 bands × 4 rows):
  → Only ~0.1% of documents become candidates (hash bucket collisions)
  → 50M × 0.001 = 50,000 comparisons ≈ feasible
```

LSH acts as a **pre-filter**: it narrows down candidates using band hashing, then MinHash verifies only the candidates.

#### Decision Flowchart Summary

| Question                                                             | Answer → Action                               |
| -------------------------------------------------------------------- | --------------------------------------------- |
| Does a document with the same canonical URL exist in this WatchFile? | **Yes** → reject as duplicate (stage 0)       |
| Does a document with the same SHA256 content hash exist?             | **Yes** → reject as duplicate (stage 1)       |
| Does a document with SimHash Hamming distance ≤ 3 exist?             | **Yes** → reject/flag as near-exact (stage 2) |
| Does any LSH candidate have MinHash Jaccard ≥ 0.85?                  | **Yes** → flag as near-duplicate (stage 3)    |
| None of the above?                                                   | **Unique document** → store fingerprints      |

---

## Decision

Use a **layered approach** with OpenSearch as primary persistent storage, from cheapest to most expensive:

0. **Canonical URL** (keyword lookup): Same article syndicated under different URLs but sharing a canonical
1. **Content Hash** (SHA256): Exact duplicate detection on normalized text
2. **SimHash** (64-bit): Fast similarity check for >90% similar documents
3. **MinHash + LSH**: Near-duplicate detection (85-90% similarity)
4. **Storage**: OpenSearch (persistent, replicated) with optional Valkey cache

Stage 0 (canonical URL) is the cheapest check (~2ms keyword query) and catches the most common case: the same article
republished or syndicated across multiple sites that all reference the same canonical URL. It runs before any content
fingerprinting, saving the cost of normalization, shingle extraction, and hash computation.

### Why OpenSearch?

| Requirement       | OpenSearch                    | Valkey (current)         |
| ----------------- | ----------------------------- | ------------------------ |
| Persistence       | ✅ Native                     | ❌ Not configured        |
| Replication       | ✅ Native                     | ❌ No Sentinel           |
| Document storage  | ✅ Already used               | ❌ Not designed for      |
| Similarity search | ✅ script_score, dense_vector | ⚠️ Manual implementation |
| Rebuild on crash  | ✅ Survives restart           | ❌ Full reindex needed   |

---

## Architecture Overview

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                     DOCUMENT DEDUPLICATION PIPELINE                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Stage 0: CANONICAL URL CHECK (cheapest — ~2ms)                             │
│  ┌────────────────────────────────────────────────────────────────────┐     │
│  │  Extract canonical URL from:                                       │     │
│  │    1. Provider metadata (if supplied)                              │     │
│  │    2. HTML <link rel="canonical"> tag                              │     │
│  │    3. Fallback: normalize collected URL                            │     │
│  │  → Keyword lookup in OpenSearch on canonical_url field             │     │
│  │  → Match found? → EXACT duplicate, skip all fingerprinting        │     │
│  └────────────────────────────────────────────────────────────────────┘     │
│                              ↓ (no canonical match)                         │
│                                                                             │
│  Stage 1-3: CONTENT FINGERPRINTING (expensive — ~50ms)                      │
│  ┌────────────────────────────────────────────────────────────────────┐     │
│  │  Normalizer  ──►  Shingle      ──►  Fingerprint                    │     │
│  │  (lowercase,      Extractor         Generator                      │     │
│  │   strip HTML,     (3-word                                          │     │
│  │   whitespace)      shingles)        ┌──► SHA256 (exact content)    │     │
│  │                                     ├──► SimHash (64-bit)          │     │
│  │                                     └──► MinHash (128 vals)        │     │
│  └────────────────────────────────────────────────────────────────────┘     │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   STORAGE LAYER                                                             │
│                                                                             │
│   ┌───────────────────────────────────┐     ┌─────────────────────────────┐ │
│   │   OpenSearch (PRIMARY)            │     │   Valkey (CACHE)            │ │
│   │   - document.canonical_url field  │     │   - Hot canonical URL cache │ │
│   │   - document.fingerprint field    │     │   - Hot fingerprint cache   │ │
│   │   - Persistent, replicated        │     │   - Rebuildable from OS     │ │
│   │   - LSH band index                │     │   - TTL-based expiration    │ │
│   └───────────────────────────────────┘     └─────────────────────────────┘ │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   QUERY FLOW                                                                │
│                                                                             │
│   New Doc ──► Compute     ──► Check Valkey ──► Cache    ──► Check           │
│               Fingerprints     Cache           Hit?         OpenSearch      │
│                                  │              │                │          │
│                                  │ Miss         │ Yes            │          │
│                                  ▼              ▼                ▼          │
│                              Query OS      Return Match     Return Match    │
│                              + Cache                        or Store New    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Stage 0: Canonical URL Deduplication

### Rationale

The most common duplicate scenario is the same article syndicated across multiple sites (news agencies, press
releases, content aggregators). These pages often declare the same canonical URL via `<link rel="canonical">`. Checking
this URL is orders of magnitude cheaper than content fingerprinting and catches the majority of exact duplicates.

### Canonical URL Sources (Priority Order)

The canonical URL is not always available. We resolve it from multiple sources with a fallback chain:

| Priority | Source                                            | Availability                          | Reliability                        |
| -------- | ------------------------------------------------- | ------------------------------------- | ---------------------------------- |
| 1        | Provider metadata (`canonical_url` field)         | Rare — most providers don't supply it | High when present                  |
| 2        | HTML `<link rel="canonical" href="...">` tag      | Common on news/media sites (~70%)     | High — publisher intent            |
| 3        | HTML `<meta property="og:url" content="...">` tag | Common (~60%)                         | Medium — sometimes differs         |
| 4        | Normalized collected URL (fallback)               | Always available                      | Low — URL may have tracking params |

**Important**: The canonical URL may point to a different domain (e.g. syndicated article pointing back to original
publisher). This is expected and desirable — it identifies the original source.

### Canonical URL Extraction

```php
final readonly class CanonicalUrlExtractor
{
    /**
     * Extract canonical URL from available sources.
     *
     * Priority:
     * 1. Provider-supplied canonical URL (if present in metadata)
     * 2. <link rel="canonical"> from HTML
     * 3. <meta property="og:url"> from HTML
     * 4. Normalized version of collected URL (fallback)
     */
    public function extract(?string $providerCanonical, ?string $rawHtml, string $collectedUrl): string
    {
        // 1. Provider metadata (trusted if present)
        if ($providerCanonical !== null && $providerCanonical !== '') {
            return $this->normalizeUrl($providerCanonical);
        }

        // 2-3. Extract from HTML
        if ($rawHtml !== null) {
            $fromHtml = $this->extractFromHtml($rawHtml);
            if ($fromHtml !== null) {
                return $this->normalizeUrl($fromHtml);
            }
        }

        // 4. Fallback: normalize collected URL
        return $this->normalizeUrl($collectedUrl);
    }

    private function extractFromHtml(string $html): ?string
    {
        // Use regex instead of DOM parsing for performance on large HTML
        // <link rel="canonical" href="...">
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        // <meta property="og:url" content="...">
        if (preg_match('/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Normalize URL for deduplication comparison.
     *
     * Operations:
     * 1. Parse and reconstruct (removes fragments)
     * 2. Lowercase scheme and host
     * 3. Remove common tracking parameters (utm_*, fbclid, gclid, etc.)
     * 4. Remove trailing slash on path
     * 5. Sort remaining query parameters for consistency
     */
    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && $parts['port'] !== 443 && $parts['port'] !== 80
            ? ':' . $parts['port'] : '';
        $path = rtrim($parts['path'] ?? '/', '/') ?: '/';

        // Remove tracking params
        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            $params = $this->removeTrackingParams($params);
            if (!empty($params)) {
                ksort($params);
                $query = '?' . http_build_query($params);
            }
        }

        return sprintf('%s://%s%s%s%s', $scheme, $host, $port, $path, $query);
    }

    private const array TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid', 'gclsrc', 'dclid', 'msclkid',
        'mc_cid', 'mc_eid', 'ref', '_ga', '_gl',
    ];

    private function removeTrackingParams(array $params): array
    {
        return array_filter(
            $params,
            fn(string $key) => !in_array(strtolower($key), self::TRACKING_PARAMS, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
```

### When to Extract

Canonical URL extraction runs as part of the **enrichment phase** (ADR-003, priority 200+) in the document processing
pipeline. It runs before quality scoring and deduplication:

```php
final readonly class CanonicalUrlEnrichmentProcessor implements DocumentProcessorInterface
{
    public function __construct(
        private CanonicalUrlExtractor $extractor,
    ) {}

    public function priority(): int
    {
        return 210; // Early enrichment, before deduplication
    }

    public function supports(ProcessingContext $context): bool
    {
        // Run if document has no canonical URL yet
        return $context->document->getCanonicalUrl() === null;
    }

    public function process(ProcessingContext $context): ProcessingContext
    {
        $document = $context->document;

        $canonicalUrl = $this->extractor->extract(
            providerCanonical: $document->getProviderCanonicalUrl(),
            rawHtml: $document->getRawHtml(),
            collectedUrl: $document->getUrl(),
        );

        $document->setCanonicalUrl($canonicalUrl);

        return $context;
    }
}
```

### OpenSearch Mapping

```json
{
  "mappings": {
    "properties": {
      "canonical_url": {
        "type": "keyword",
        "doc_values": true
      }
    }
  }
}
```

### Deduplication Query

```php
/**
 * Check for existing document with same canonical URL in same WatchFile.
 * O(1) keyword lookup — cheapest deduplication check.
 */
public function findByCanonicalUrl(
    string $canonicalUrl,
    WatchFileId $watchFileId,
): ?DocumentId {
    $query = [
        'bool' => [
            'filter' => [
                ['term' => ['watch_file_id' => $watchFileId->toString()]],
                ['term' => ['canonical_url' => $canonicalUrl]],
            ],
        ],
    ];

    $result = $this->client->search([
        'index' => 'documents',
        'body' => ['query' => $query, 'size' => 1, '_source' => false],
    ]);

    // ... return DocumentId if found
}
```

### Limitations

| Limitation                             | Impact                                       | Mitigation                                                    |
| -------------------------------------- | -------------------------------------------- | ------------------------------------------------------------- |
| No canonical tag in HTML               | ~30% of pages, especially older sites        | Fallback to normalized collected URL                          |
| Canonical URL pointing to wrong page   | Rare but possible (misconfigured CMS)        | Content fingerprinting (stages 1-3) catches divergent content |
| Normalized URL collision               | Two different pages with same normalized URL | Very rare, acceptable false positive                          |
| Provider doesn't supply metadata       | Most providers currently                     | HTML extraction covers most cases                             |
| No HTML available (e.g. PDF, API-only) | Cannot extract canonical                     | Falls through to content fingerprinting                       |

---

## Technical Requirements

### PHP Extensions

```yaml
# Required PHP extensions
php:
  extensions:
    - gmp # GNU Multiple Precision - required for 64-bit SimHash operations
    - intl # For proper Unicode text normalization
```

**Why GMP?**

- PHP integers are limited to 64 bits but signed (max 2^63-1)
- SimHash requires unsigned 64-bit XOR and popcount operations
- GMP provides `gmp_xor()` and `gmp_popcount()` for arbitrary precision

### OpenSearch Compatibility

```yaml
# Version compatibility
development:
  elasticsearch: '9.x'

production:
  opensearch: '2.x' # Fork of ES 7.10, API compatible


# Code must use:
# - OpenSearch-compatible API calls
# - No ES-specific features (ML, security realm)
# - Compatible field types (dense_vector syntax differs slightly)
```

---

## Domain Model

### DocumentFingerprint Value Object

```php
final readonly class DocumentFingerprint
{
    public function __construct(
        public DocumentId $documentId,
        public WatchFileId $watchFileId,
        public ?string $canonicalUrl,     // Normalized canonical URL (stage 0)
        public string $simHash,           // 64-bit as hex string (16 chars)
        public array $minHashSignature,   // 128 uint32 values
        public string $contentHash,       // SHA256 for exact match
        public array $lshBandHashes,      // 32 band hashes for LSH indexing
        public int $wordCount,            // For sanity checks
        public \DateTimeImmutable $computedAt,
    ) {}

    /**
     * Compute Hamming distance between two SimHash values.
     * Returns number of differing bits (0-64).
     *
     * Requires ext-gmp for 64-bit operations.
     */
    public function hammingDistance(self $other): int
    {
        $thisHash = gmp_init($this->simHash, 16);
        $otherHash = gmp_init($other->simHash, 16);

        $xor = gmp_xor($thisHash, $otherHash);

        return gmp_popcount($xor);
    }

    /**
     * Estimate Jaccard similarity from MinHash signatures.
     * Returns value between 0.0 and 1.0.
     */
    public function estimatedJaccard(self $other): float
    {
        $matches = 0;
        $total = count($this->minHashSignature);

        for ($i = 0; $i < $total; $i++) {
            if ($this->minHashSignature[$i] === $other->minHashSignature[$i]) {
                $matches++;
            }
        }

        return $matches / $total;
    }

    /**
     * Convert Hamming distance to approximate similarity percentage.
     */
    public function hammingToSimilarity(int $hammingDistance): float
    {
        // 64-bit hash: each bit represents ~1.56% of similarity
        return 1.0 - ($hammingDistance / 64.0);
    }
}
```

### DuplicateMatch Value Object

```php
final readonly class DuplicateMatch
{
    public function __construct(
        public DocumentId $documentId,
        public float $similarity,          // 0.0 - 1.0
        public DuplicateType $type,
        public string $matchMethod,        // 'content_hash', 'simhash', 'minhash_lsh'
        public \DateTimeImmutable $indexedAt,
    ) {}
}

enum DuplicateType: string
{
    case EXACT = 'exact';                  // Content hash match (100%)
    case NEAR_EXACT = 'near_exact';        // SimHash Hamming ≤ 3 (>95%)
    case NEAR_DUPLICATE = 'near_duplicate';// MinHash Jaccard ≥ 0.85 (85-95%)
}
```

---

## Multilingual Support

### Language Compatibility Matrix

| Layer                         | Latin (EN, FR, DE...) | Cyrillic (RU, UK...) | Devanagari (HI) | Arabic (AR) | CJK (ZH, JA, KO)                    |
| ----------------------------- | --------------------- | -------------------- | --------------- | ----------- | ----------------------------------- |
| Canonical URL (stage 0)       | OK                    | OK                   | OK              | OK          | OK                                  |
| SHA256 content hash (stage 1) | OK                    | OK                   | OK              | OK          | OK                                  |
| TextNormalizer                | OK                    | OK                   | OK              | OK          | OK                                  |
| ShingleExtractor (word-level) | OK                    | OK                   | OK              | OK          | **BROKEN**                          |
| SimHash / MinHash (stage 2-3) | OK                    | OK                   | OK              | OK          | **BROKEN** (inherits shingle issue) |

**Root cause**: The `ShingleExtractor` uses `explode(' ', $text)` to split into words. Chinese, Japanese, and Korean
(CJK) scripts do not use spaces between words. A Japanese sentence like `東京都は日本の首都です` is treated as a single
"word", producing one giant shingle — making SimHash/MinHash useless.

Russian, Hindi, Arabic, and other space-separated scripts work fine because `mb_strtolower()` and `\p{L}` Unicode
character classes handle their alphabets correctly.

### Solution: Hybrid Shingle Strategy

For CJK text, fall back to **character n-grams** instead of word n-grams. Character n-grams don't require word
boundaries and work across all scripts:

```text
Word shingles (French):     "le président français" → ["le président français"]
Character 4-grams (Japanese): "東京都は日本" → ["東京都は", "京都は日", "都は日本"]
```

**Detection**: Use Unicode script detection to identify CJK content. If the text contains a significant proportion
of CJK characters (>30%), switch to character n-gram mode.

```php
final readonly class ScriptDetector
{
    /**
     * Detect if text is predominantly CJK (Chinese, Japanese, Korean).
     *
     * CJK Unicode ranges:
     * - CJK Unified Ideographs: U+4E00–U+9FFF (Chinese/Japanese Kanji)
     * - Hiragana: U+3040–U+309F (Japanese)
     * - Katakana: U+30A0–U+30FF (Japanese)
     * - Hangul Syllables: U+AC00–U+D7AF (Korean)
     * - CJK Extension A/B: U+3400–U+4DBF, U+20000–U+2A6DF
     */
    private const string CJK_PATTERN =
        '/[\x{4E00}-\x{9FFF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}\x{3400}-\x{4DBF}]/u';

    public function isCjkDominant(string $text, float $threshold = 0.30): bool
    {
        $totalChars = mb_strlen($text, 'UTF-8');
        if ($totalChars === 0) {
            return false;
        }

        preg_match_all(self::CJK_PATTERN, $text, $matches);
        $cjkChars = count($matches[0]);

        return ($cjkChars / $totalChars) >= $threshold;
    }
}
```

### Adapted ShingleExtractor

The `ShingleExtractor` selects its strategy based on script detection:

- **Space-separated scripts** (Latin, Cyrillic, Devanagari, Arabic, etc.): 3-word shingles (current behavior)
- **CJK scripts**: 4-character n-grams (no word boundary needed)

```php
final readonly class ShingleExtractor
{
    private const int WORD_SHINGLE_SIZE = 3;
    private const int CHAR_NGRAM_SIZE = 4;

    public function __construct(
        private ScriptDetector $scriptDetector,
    ) {}

    /**
     * Extract shingles using the appropriate strategy for the text's script.
     *
     * @return string[] Unique shingles
     */
    public function extract(string $normalizedText): array
    {
        if ($this->scriptDetector->isCjkDominant($normalizedText)) {
            return $this->extractCharNgrams($normalizedText);
        }

        return $this->extractWordShingles($normalizedText);
    }

    /**
     * Character n-grams for CJK text.
     *
     * Why 4 characters?
     * - 2 chars: too generic (many collisions in CJK)
     * - 3 chars: borderline, Japanese has many 3-char compounds
     * - 4 chars: good specificity, captures compound words
     * - 5+ chars: too specific, misses minor rewording
     */
    private function extractCharNgrams(string $text): array
    {
        // Remove spaces (CJK text may have sporadic spaces)
        $text = preg_replace('/\s+/u', '', $text);
        $length = mb_strlen($text, 'UTF-8');

        if ($length < self::CHAR_NGRAM_SIZE) {
            return $text !== '' ? [$text] : [];
        }

        $ngrams = [];
        $limit = $length - self::CHAR_NGRAM_SIZE + 1;

        for ($i = 0; $i < $limit; $i++) {
            $ngram = mb_substr($text, $i, self::CHAR_NGRAM_SIZE, 'UTF-8');
            $ngrams[$ngram] = true;
        }

        return array_keys($ngrams);
    }

    /**
     * Word-level shingles for space-separated scripts.
     */
    private function extractWordShingles(string $text): array
    {
        $words = explode(' ', $text);
        $wordCount = count($words);

        if ($wordCount < self::WORD_SHINGLE_SIZE) {
            return $text !== '' ? [$text] : [];
        }

        $shingles = [];
        $limit = $wordCount - self::WORD_SHINGLE_SIZE + 1;

        for ($i = 0; $i < $limit; $i++) {
            $shingle = implode(' ', array_slice($words, $i, self::WORD_SHINGLE_SIZE));
            $shingles[$shingle] = true;
        }

        return array_keys($shingles);
    }
}
```

### Mixed-Script Documents

Some documents contain multiple scripts (e.g. a Japanese article quoting English text, or a French article with
Chinese names). The `isCjkDominant()` threshold of 30% handles this:

- **Mostly CJK with Latin fragments** (>30% CJK): Uses character n-grams — Latin fragments become short n-grams,
  acceptable loss of specificity on the minority script
- **Mostly Latin with CJK fragments** (<30% CJK): Uses word shingles — CJK fragments become single large "words",
  minor degradation on CJK portions

This is a pragmatic tradeoff. A document comparison always happens within the same WatchFile, and WatchFiles
typically focus on a single language or region. Mixed-script comparison across very different languages is rare
and less critical.

### Impact on Existing Algorithms

| Algorithm        | Change Required | Notes                                            |
| ---------------- | --------------- | ------------------------------------------------ |
| TextNormalizer   | None            | Already Unicode-aware (`mb_strtolower`, `\p{L}`) |
| ShingleExtractor | **Modified**    | Hybrid word/character n-gram strategy            |
| ScriptDetector   | **New class**   | CJK detection via Unicode ranges                 |
| SimHashGenerator | None            | Operates on shingle strings (bytes), agnostic    |
| MinHashGenerator | None            | Operates on shingle strings (bytes), agnostic    |
| LshBandGenerator | None            | Operates on MinHash values, agnostic             |
| SHA256           | None            | Bytes, fully agnostic                            |
| Canonical URL    | None            | URL, fully agnostic                              |

---

## Algorithm Implementation

### 1. Text Normalization

```php
final readonly class TextNormalizer
{
    /**
     * Normalize text for fingerprinting.
     *
     * Operations applied:
     * 1. Strip HTML tags
     * 2. Decode HTML entities
     * 3. Convert to lowercase (Unicode-aware)
     * 4. Remove punctuation, keep alphanumeric + spaces
     * 5. Collapse multiple whitespace to single space
     */
    public function normalize(string $text): string
    {
        // 1. Strip HTML tags
        $text = strip_tags($text);

        // 2. Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 3. Lowercase (Unicode-aware)
        $text = mb_strtolower($text, 'UTF-8');

        // 4. Remove punctuation (keep letters, numbers, whitespace)
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        // 5. Collapse whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }
}
```

### 2. Shingle Extraction

See **Multilingual Support** section above for the full `ShingleExtractor` and `ScriptDetector` implementation.

The extractor uses a hybrid strategy:

- **Space-separated scripts**: 3-word shingles (`"the quick brown"`, `"quick brown fox"`)
- **CJK scripts**: 4-character n-grams (`"東京都は"`, `"京都は日"`)

### 3. SimHash Generation

```php
final readonly class SimHashGenerator
{
    /**
     * SimHash bit size: 64 bits.
     *
     * Why 64 bits?
     * - 32 bits: too many collisions at scale
     * - 64 bits: good balance, fits in standard integer types
     * - 128 bits: diminishing returns, more storage
     */
    private const int HASH_BITS = 64;

    /**
     * Generate a 64-bit SimHash from shingles.
     *
     * Algorithm:
     * 1. Initialize 64 counters to 0
     * 2. For each shingle:
     *    a. Hash shingle to 64 bits
     *    b. For each bit: if 1, increment counter; if 0, decrement
     * 3. Final hash: positive counters → 1, negative → 0
     *
     * @param string[] $shingles
     * @return string 16-character hex string (64 bits)
     */
    public function generate(array $shingles): string
    {
        if (empty($shingles)) {
            return str_repeat('0', 16);
        }

        // Initialize bit counters
        $bitCounts = array_fill(0, self::HASH_BITS, 0);

        foreach ($shingles as $shingle) {
            // Hash shingle to 64-bit integer
            $hash = $this->hashShingle($shingle);

            // Update counters based on each bit
            for ($i = 0; $i < self::HASH_BITS; $i++) {
                if (($hash >> $i) & 1) {
                    $bitCounts[$i]++;
                } else {
                    $bitCounts[$i]--;
                }
            }
        }

        // Build final hash from counter signs
        $simHash = gmp_init(0);
        for ($i = 0; $i < self::HASH_BITS; $i++) {
            if ($bitCounts[$i] > 0) {
                $simHash = gmp_setbit($simHash, $i);
            }
        }

        // Return as 16-char hex string (64 bits)
        return str_pad(gmp_strval($simHash, 16), 16, '0', STR_PAD_LEFT);
    }

    /**
     * Hash a shingle to a 64-bit integer.
     * Uses xxHash for speed (faster than MD5/SHA).
     */
    private function hashShingle(string $shingle): int
    {
        // xxh3 returns 16 hex chars = 64 bits
        $hex = hash('xxh3', $shingle);

        return gmp_intval(gmp_init($hex, 16));
    }
}
```

### 4. MinHash Signature Generation

```php
final readonly class MinHashGenerator
{
    /**
     * Number of hash functions in the signature.
     *
     * Why 128?
     * - Standard error = 1/√k ≈ 8.8% for k=128
     * - Good balance of accuracy vs. storage (512 bytes)
     * - Divisible by common band sizes (32 bands × 4 rows)
     */
    private const int NUM_HASHES = 128;

    /**
     * Large prime for hash function modulo.
     */
    private const int PRIME = 2147483647; // 2^31 - 1 (Mersenne prime)

    /**
     * Pre-computed hash function parameters.
     * @var array<int, array{a: int, b: int}>
     */
    private array $hashFunctions;

    public function __construct()
    {
        $this->hashFunctions = $this->initializeHashFunctions();
    }

    /**
     * Generate MinHash signature from shingles.
     *
     * Algorithm:
     * 1. For each of k hash functions, find the minimum hash value
     *    across all shingles
     * 2. Return array of k minimum values
     *
     * @param string[] $shingles
     * @return int[] Array of 128 uint32 values
     */
    public function generate(array $shingles): array
    {
        $signature = array_fill(0, self::NUM_HASHES, PHP_INT_MAX);

        foreach ($shingles as $shingle) {
            $shingleHash = crc32($shingle);

            for ($i = 0; $i < self::NUM_HASHES; $i++) {
                $hashValue = $this->applyHashFunction($i, $shingleHash);
                $signature[$i] = min($signature[$i], $hashValue);
            }
        }

        return $signature;
    }

    /**
     * Initialize hash function parameters.
     *
     * Each function is: h_i(x) = (a_i * x + b_i) mod p
     * Using deterministic seed for reproducibility across instances.
     */
    private function initializeHashFunctions(): array
    {
        $functions = [];

        // Deterministic seed for reproducibility
        mt_srand(42);

        for ($i = 0; $i < self::NUM_HASHES; $i++) {
            $functions[$i] = [
                'a' => mt_rand(1, self::PRIME - 1),
                'b' => mt_rand(0, self::PRIME - 1),
            ];
        }

        // Reset random seed
        mt_srand();

        return $functions;
    }

    /**
     * Apply hash function i to value x.
     * h_i(x) = (a_i * x + b_i) mod p
     */
    private function applyHashFunction(int $index, int $value): int
    {
        $f = $this->hashFunctions[$index];

        // Use GMP to avoid integer overflow
        $result = gmp_mod(
            gmp_add(
                gmp_mul($f['a'], $value),
                $f['b']
            ),
            self::PRIME
        );

        return gmp_intval($result);
    }
}
```

### 5. LSH Band Hash Generation

```php
final readonly class LshBandGenerator
{
    /**
     * Number of bands for LSH indexing.
     *
     * With 128 MinHash values and 32 bands:
     * - 4 rows per band (128 / 32)
     * - ~97% chance of matching documents with Jaccard ≥ 0.80
     * - ~18% chance of matching documents with Jaccard = 0.50
     */
    private const int NUM_BANDS = 32;
    private const int ROWS_PER_BAND = 4; // 128 / 32

    /**
     * Generate LSH band hashes from MinHash signature.
     *
     * Each band hash is a hash of ROWS_PER_BAND consecutive MinHash values.
     * Documents matching in ANY band are candidates for similarity check.
     *
     * @param int[] $minHashSignature Array of 128 values
     * @return string[] Array of 32 band hashes (MD5 hex)
     */
    public function generate(array $minHashSignature): array
    {
        $bandHashes = [];

        for ($band = 0; $band < self::NUM_BANDS; $band++) {
            $start = $band * self::ROWS_PER_BAND;
            $bandValues = array_slice($minHashSignature, $start, self::ROWS_PER_BAND);

            // Hash the band values to a single string
            $bandHashes[$band] = md5(implode(':', $bandValues));
        }

        return $bandHashes;
    }
}
```

---

## OpenSearch Integration

### Index Mapping

```json
{
  "mappings": {
    "properties": {
      "fingerprint": {
        "properties": {
          "content_hash": {
            "type": "keyword",
            "doc_values": true
          },
          "sim_hash": {
            "type": "keyword",
            "doc_values": true
          },
          "min_hash_signature": {
            "type": "integer",
            "index": false,
            "doc_values": true
          },
          "lsh_bands": {
            "type": "keyword",
            "doc_values": true
          },
          "word_count": {
            "type": "integer"
          },
          "computed_at": {
            "type": "date"
          }
        }
      }
    }
  }
}
```

### Query: Exact Duplicate Check

```php
/**
 * Check for exact duplicate by content hash.
 * O(1) lookup via keyword term query.
 */
public function findExactDuplicate(
    string $contentHash,
    WatchFileId $watchFileId,
): ?DocumentFingerprint {
    $query = [
        'bool' => [
            'filter' => [
                ['term' => ['watch_file_id' => $watchFileId->toString()]],
                ['term' => ['fingerprint.content_hash' => $contentHash]],
            ],
        ],
    ];

    $result = $this->client->search([
        'index' => 'documents',
        'body' => ['query' => $query, 'size' => 1],
    ]);

    // ... map result to DocumentFingerprint
}
```

### Query: LSH Candidate Search

```php
/**
 * Find candidate documents using LSH band matching.
 * Documents sharing ANY band hash are potential near-duplicates.
 */
public function findLshCandidates(
    array $lshBandHashes,
    WatchFileId $watchFileId,
    int $limit = 50,
): array {
    $query = [
        'bool' => [
            'filter' => [
                ['term' => ['watch_file_id' => $watchFileId->toString()]],
            ],
            'should' => array_map(
                fn($hash) => ['term' => ['fingerprint.lsh_bands' => $hash]],
                $lshBandHashes
            ),
            'minimum_should_match' => 1,
        ],
    ];

    $result = $this->client->search([
        'index' => 'documents',
        'body' => [
            'query' => $query,
            'size' => $limit,
            '_source' => ['fingerprint'],
        ],
    ]);

    // ... map results and verify similarity with MinHash comparison
}
```

---

## Duplicate Detection Service

```php
final readonly class DuplicateDetector implements DuplicateDetectorInterface
{
    private const float EXACT_THRESHOLD = 0.98;
    private const float NEAR_DUPLICATE_THRESHOLD = 0.85;
    private const int SIMHASH_HAMMING_THRESHOLD = 3; // ~95% similarity

    public function __construct(
        private OpenSearchFingerprintRepository $repository,
        private ?ValkeyCacheAdapter $cache = null,
    ) {}

    /**
     * Find similar documents for a given fingerprint.
     *
     * Strategy (cheapest to most expensive):
     * 0. Check canonical URL for exact match (~2ms keyword lookup)
     * 1. Check content hash for exact match (SHA256 keyword lookup)
     * 2. Check SimHash Hamming distance for near-exact (fast bitwise)
     * 3. Use LSH to find candidates, verify with MinHash (thorough)
     */
    public function findSimilar(
        DocumentFingerprint $fingerprint,
        WatchFileId $watchFileId,
        float $threshold = self::NEAR_DUPLICATE_THRESHOLD,
        int $limit = 10,
    ): array {
        $matches = [];

        // Stage 0: Canonical URL check (cheapest — keyword lookup)
        if ($fingerprint->canonicalUrl !== null) {
            $canonicalMatch = $this->repository->findByCanonicalUrl(
                $fingerprint->canonicalUrl,
                $watchFileId
            );

            if ($canonicalMatch !== null && !$canonicalMatch->equals($fingerprint->documentId)) {
                return [new DuplicateMatch(
                    documentId: $canonicalMatch,
                    similarity: 1.0,
                    type: DuplicateType::EXACT,
                    matchMethod: 'canonical_url',
                    indexedAt: new \DateTimeImmutable(),
                )];
            }
        }

        // Stage 1: Exact content hash check
        $exactMatch = $this->repository->findByContentHash(
            $fingerprint->contentHash,
            $watchFileId
        );

        if ($exactMatch !== null && !$exactMatch->documentId->equals($fingerprint->documentId)) {
            return [new DuplicateMatch(
                documentId: $exactMatch->documentId,
                similarity: 1.0,
                type: DuplicateType::EXACT,
                matchMethod: 'content_hash',
                indexedAt: $exactMatch->computedAt,
            )];
        }

        // Stage 2: LSH candidate search + SimHash/MinHash verification
        $candidates = $this->repository->findLshCandidates(
            $fingerprint->lshBandHashes,
            $watchFileId,
            limit: 50
        );

        foreach ($candidates as $candidate) {
            if ($candidate->documentId->equals($fingerprint->documentId)) {
                continue;
            }

            // Quick SimHash check first
            $hammingDist = $fingerprint->hammingDistance($candidate);
            if ($hammingDist <= self::SIMHASH_HAMMING_THRESHOLD) {
                $similarity = $fingerprint->hammingToSimilarity($hammingDist);
                $matches[] = new DuplicateMatch(
                    documentId: $candidate->documentId,
                    similarity: $similarity,
                    type: DuplicateType::NEAR_EXACT,
                    matchMethod: 'simhash',
                    indexedAt: $candidate->computedAt,
                );
                continue;
            }

            // Full MinHash Jaccard estimation
            $jaccard = $fingerprint->estimatedJaccard($candidate);
            if ($jaccard >= $threshold) {
                $matches[] = new DuplicateMatch(
                    documentId: $candidate->documentId,
                    similarity: $jaccard,
                    type: DuplicateType::NEAR_DUPLICATE,
                    matchMethod: 'minhash_lsh',
                    indexedAt: $candidate->computedAt,
                );
            }
        }

        // Sort by similarity descending
        usort($matches, fn($a, $b) => $b->similarity <=> $a->similarity);

        return array_slice($matches, 0, $limit);
    }
}
```

---

## Valkey Cache Layer (Optional)

```php
/**
 * Optional cache layer for hot fingerprint lookups.
 *
 * Cache is NOT required for correctness - OpenSearch is source of truth.
 * Cache improves latency for frequently checked domains/content hashes.
 */
final readonly class ValkeyCacheAdapter
{
    private const int TTL_SECONDS = 3600; // 1 hour

    public function __construct(
        private \Redis $valkey,
    ) {}

    public function getCachedContentHash(string $contentHash, WatchFileId $wfId): ?string
    {
        $key = sprintf('dedup:hash:%s:%s', $wfId->toString(), $contentHash);
        $result = $this->valkey->get($key);

        return $result !== false ? $result : null;
    }

    public function cacheContentHash(
        string $contentHash,
        WatchFileId $wfId,
        DocumentId $docId,
    ): void {
        $key = sprintf('dedup:hash:%s:%s', $wfId->toString(), $contentHash);
        $this->valkey->setex($key, self::TTL_SECONDS, $docId->toString());
    }

    /**
     * Warm cache from OpenSearch on startup or after cache clear.
     * Called by scheduled job, not on every request.
     */
    public function warmCache(WatchFileId $watchFileId): void
    {
        // Load recent fingerprints from OpenSearch and populate cache
    }
}
```

---

## End-to-End Deduplication Flow

This section describes the complete journey of an incoming document through the deduplication pipeline: what
happens at each stage, what decision is taken, and what the possible outcomes are.

### Overview: 5 Possible Outcomes

Every incoming document ends in exactly one of these outcomes:

| Outcome                       | Decision            | Document is...                        | Fingerprints stored?     |
| ----------------------------- | ------------------- | ------------------------------------- | ------------------------ |
| **DUPLICATE (canonical URL)** | Rejected            | Identical article under different URL | No                       |
| **DUPLICATE (exact content)** | Rejected            | Byte-identical after normalization    | No                       |
| **NEAR-EXACT (SimHash)**      | Rejected or flagged | >95% similar (minor edits)            | No                       |
| **NEAR-DUPLICATE (MinHash)**  | Flagged for review  | 85-95% similar (partial rewrite)      | Yes (linked to original) |
| **UNIQUE**                    | Accepted            | <85% similar to any existing doc      | Yes                      |

### Step-by-Step Flow

```text
 INCOMING DOCUMENT
        │
        ▼
 ┌──────────────────────────────────────────────────────────────────────┐
 │  STAGE 0 — Canonical URL                                            │
 │                                                                      │
 │  1. Extract canonical URL:                                           │
 │     provider metadata → <link rel="canonical"> → <meta og:url>      │
 │     → fallback: normalize collected URL                              │
 │                                                                      │
 │  2. Query OpenSearch:                                                │
 │     WHERE watch_file_id = :wf AND canonical_url = :url               │
 │                                                                      │
 │  3. Decision:                                                        │
 │     ┌─────────────┐                                                  │
 │     │ Match found? │──── YES ──► REJECT: duplicate of doc #X         │
 │     └──────┬──────┘            (link to original, skip stages 1-3)  │
 │            │ NO                                                      │
 └────────────┼─────────────────────────────────────────────────────────┘
              ▼
 ┌──────────────────────────────────────────────────────────────────────┐
 │  CONTENT FINGERPRINTING (computed once, used by stages 1-3)          │
 │                                                                      │
 │  1. Normalize text (strip HTML, lowercase, remove punctuation)       │
 │  2. Extract shingles (3-word or 4-char for CJK)                     │
 │  3. Compute in parallel:                                             │
 │     • SHA256(normalized_text) ──► content_hash                       │
 │     • SimHash(shingles) ──► 64-bit sim_hash                          │
 │     • MinHash(shingles) ──► 128-value signature                      │
 │     • LSH bands(minhash) ──► 32 band hashes                          │
 └──────────────────────┬───────────────────────────────────────────────┘
                        ▼
 ┌──────────────────────────────────────────────────────────────────────┐
 │  STAGE 1 — SHA256 Content Hash                                       │
 │                                                                      │
 │  Query OpenSearch:                                                   │
 │  WHERE watch_file_id = :wf AND fingerprint.content_hash = :hash      │
 │                                                                      │
 │  ┌─────────────┐                                                     │
 │  │ Match found? │──── YES ──► REJECT: exact duplicate of doc #X      │
 │  └──────┬──────┘            (identical content, different URL)       │
 │         │ NO                                                         │
 └─────────┼────────────────────────────────────────────────────────────┘
           ▼
 ┌──────────────────────────────────────────────────────────────────────┐
 │  STAGE 2 — SimHash (Hamming Distance)                                │
 │                                                                      │
 │  Search LSH candidates first, then check SimHash among them:         │
 │  FOR EACH candidate WHERE any LSH band matches:                      │
 │      hamming = popcount(sim_hash XOR candidate.sim_hash)             │
 │                                                                      │
 │  ┌────────────────┐                                                  │
 │  │ hamming ≤ 1 ?  │──── YES ──► REJECT: near-exact duplicate         │
 │  └───────┬────────┘            (>98% similar, e.g. typo fix)        │
 │          │ NO                                                        │
 │  ┌────────────────┐                                                  │
 │  │ hamming ≤ 3 ?  │──── YES ──► FLAG: likely duplicate               │
 │  └───────┬────────┘            (95-98%, e.g. date/footer change)    │
 │          │ NO                                                        │
 └──────────┼───────────────────────────────────────────────────────────┘
            ▼
 ┌──────────────────────────────────────────────────────────────────────┐
 │  STAGE 3 — MinHash + LSH (Jaccard Estimation)                        │
 │                                                                      │
 │  FOR EACH remaining LSH candidate (not caught by SimHash):           │
 │      jaccard = count(matching_positions) / 128                       │
 │                                                                      │
 │  ┌──────────────────┐                                                │
 │  │ jaccard ≥ 0.85 ? │──── YES ──► FLAG: near-duplicate               │
 │  └───────┬──────────┘            (85-95%, e.g. partial rewrite)     │
 │          │ NO                                                        │
 └──────────┼───────────────────────────────────────────────────────────┘
            ▼
 ┌──────────────────────────────────────────────────────────────────────┐
 │  NO MATCH — UNIQUE DOCUMENT                                          │
 │                                                                      │
 │  → Store all fingerprints in OpenSearch:                              │
 │    canonical_url, content_hash, sim_hash, min_hash, lsh_bands        │
 │  → Populate Valkey cache (if enabled)                                │
 │  → Continue to quality scoring pipeline (ADR-003/004/005)            │
 └──────────────────────────────────────────────────────────────────────┘
```

### Reject vs. Flag

La pipeline produit deux types de résultats positifs qui sont traités différemment :

| Résultat   | Similarité | Action                                                                           | Justification                                                                                                                                           |
| ---------- | ---------- | -------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **REJECT** | ≥95%       | Document silencieusement écarté, non indexé                                      | Contenu quasi-identique, aucune valeur ajoutée pour l'utilisateur                                                                                       |
| **FLAG**   | 85-95%     | Document indexé mais marqué `is_near_duplicate = true`, lié au document original | Contenu suffisamment différent pour avoir de la valeur (reformulation, ajouts éditoriaux) mais l'utilisateur doit savoir qu'un article similaire existe |

Le seuil de 95% (Hamming ≤ 3) pour la frontière reject/flag est configurable par WatchFile. Certains cas d'usage
(veille concurrentielle) préfèrent voir toutes les reprises ; d'autres (veille réglementaire) ne veulent que l'original.

### Concrete Examples

#### Example A — AFP Wire Article (caught at Stage 0)

```text
Document entrant:
  URL:       https://lefigaro.fr/flash-actu/2026/01/15/europe-trade-deal
  HTML:      <link rel="canonical" href="https://afp.com/article/europe-trade-deal-2026">
  Contenu:   "L'Union européenne a conclu un accord commercial..."

Pipeline:
  Stage 0 → canonical_url = "https://afp.com/article/europe-trade-deal-2026"
          → OpenSearch: match! doc #4521 (lemonde.fr) has same canonical URL
          → REJECT (duplicate of #4521)
          → Stages 1-3 never executed (économie ~50ms)
```

#### Example B — Same Article Re-crawled (caught at Stage 1)

```text
Document entrant:
  URL:       https://reuters.com/article/climate-2026 (déjà indexé il y a 2h)
  HTML:      pas de <link rel="canonical">
  Contenu:   "Global temperatures reached record levels in 2025..."

Pipeline:
  Stage 0 → canonical_url = "https://reuters.com/article/climate-2026" (normalized URL)
          → OpenSearch: no match (le premier crawl avait une URL avec ?utm_source=...)
          → continue
  Stage 1 → SHA256 = "a3f2c7..." → OpenSearch: match! doc #7890 (même contenu exact)
          → REJECT (exact duplicate of #7890)
```

#### Example C — Article with Typo Fix (caught at Stage 2)

```text
Document entrant:
  URL:       https://bbc.com/news/tech-ai-regulation-v2
  Contenu:   "The European Commission proposed new AI regulations..."
             (version corrigée: "Comission" → "Commission", date mise à jour)

Déjà indexé: doc #11234 (version originale avec typo)

Pipeline:
  Stage 0 → canonical URLs différentes → continue
  Stage 1 → SHA256 différent (1 mot changé = hash totalement différent) → continue
  Stage 2 → SimHash Hamming distance = 2 (2 bits sur 64 diffèrent)
          → 2 ≤ 3 → FLAG: likely duplicate of #11234 (similarity ~97%)
```

#### Example D — Content Farm Rewrite (caught at Stage 3)

```text
Document entrant:
  URL:       https://newsaggregator.xyz/ai-regulations-europe
  Contenu:   Article original Reuters partiellement réécrit,
             introduction et conclusion changées, corps conservé (~88% overlap)

Pipeline:
  Stage 0 → canonical URLs différentes → continue
  Stage 1 → SHA256 différent → continue
  Stage 2 → SimHash Hamming distance = 8 (trop de différences pour SimHash)
          → 8 > 3 → continue (SimHash ne peut pas conclure)
  Stage 3 → LSH: 4 bands match → candidat trouvé
          → MinHash Jaccard = 0.88 → ≥ 0.85
          → FLAG: near-duplicate of #7890 (similarity 88%)
```

#### Example E — Genuinely Unique Article

```text
Document entrant:
  URL:       https://techcrunch.com/2026/01/15/new-startup-funding
  Contenu:   Article original sur une levée de fonds

Pipeline:
  Stage 0 → pas de canonical match → continue
  Stage 1 → SHA256 unique → continue
  Stage 2 → SimHash: aucun candidat avec Hamming ≤ 3 → continue
  Stage 3 → LSH: 0 bands match (aucun candidat)
          → UNIQUE → store all fingerprints in OpenSearch
          → continue to quality pipeline
```

### Similarity Thresholds Summary

| Stage | Method        | Threshold        | Similarity     | Cost  | Outcome    |
| ----- | ------------- | ---------------- | -------------- | ----- | ---------- |
| 0     | Canonical URL | Exact URL match  | 100% (URL)     | ~2ms  | **REJECT** |
| 1     | SHA256        | Exact hash match | 100% (content) | ~2ms  | **REJECT** |
| 2     | SimHash       | Hamming ≤ 1      | >98%           | ~5ms  | **REJECT** |
| 2     | SimHash       | Hamming 2-3      | 95-98%         | ~5ms  | **FLAG**   |
| 3     | MinHash+LSH   | Jaccard ≥ 0.85   | 85-95%         | ~50ms | **FLAG**   |
| —     | —             | No match         | <85%           | —     | **UNIQUE** |

### Cumulative Cost and Recall

Pour un document entrant, le coût total dépend de l'étape à laquelle il est détecté :

| Scénario                          | Coût total                     | % des duplicates détectés (estimé) |
| --------------------------------- | ------------------------------ | ---------------------------------- |
| Rejeté au stage 0 (canonical URL) | ~2ms                           | ~40% des duplicates                |
| Rejeté au stage 1 (SHA256)        | ~4ms (0+1)                     | ~70% cumulé                        |
| Détecté au stage 2 (SimHash)      | ~55ms (0+1+fingerprinting)     | ~90% cumulé                        |
| Détecté au stage 3 (MinHash+LSH)  | ~60ms (0+1+fingerprinting+LSH) | ~95% cumulé                        |
| Unique (pas de match)             | ~60ms (coût complet)           | —                                  |

Le fingerprinting (normalization + shingles + hashes) coûte ~50ms et est mutualisé entre les stages 1, 2 et 3.
Seul le stage 0 peut l'éviter entièrement.

---

## Storage Estimates

| Component                     | Per Document | 50M Documents | Notes                         |
| ----------------------------- | ------------ | ------------- | ----------------------------- |
| OpenSearch fingerprint fields | ~700 bytes   | ~35 GB        | Included in document index    |
| Valkey cache (optional)       | ~100 bytes   | ~5 GB         | Hot entries only, TTL expires |
| **Total new storage**         | ~700 bytes   | ~35 GB        | Mostly in existing OS index   |

**Note**: Documents are already stored in OpenSearch. Fingerprint data is added as nested fields,
not a separate index. Marginal storage increase per document.

---

## Directory Structure

```text
api/src/
├── Domain/
│   └── Deduplication/
│       ├── DocumentFingerprint.php
│       ├── DuplicateMatch.php
│       ├── DuplicateType.php
│       ├── DocumentFingerprintServiceInterface.php
│       ├── DuplicateDetectorInterface.php
│       └── FingerprintRepositoryInterface.php
│
└── Infrastructure/
    └── Deduplication/
        ├── Algorithm/
        │   ├── CanonicalUrlExtractor.php
        │   ├── ScriptDetector.php
        │   ├── TextNormalizer.php
        │   ├── ShingleExtractor.php
        │   ├── SimHashGenerator.php
        │   ├── MinHashGenerator.php
        │   └── LshBandGenerator.php
        │
        ├── OpenSearch/
        │   └── OpenSearchFingerprintRepository.php
        │
        ├── Cache/
        │   └── ValkeyCacheAdapter.php
        │
        └── Service/
            ├── DocumentFingerprintService.php
            └── DuplicateDetector.php
```

---

## Options Considered

_Not documented in original ADR._

---

## Consequences

### Positive

1. **Layered cost**: Canonical URL check (~2ms) catches most duplicates before expensive fingerprinting (~50ms)
2. **Persistent storage**: Fingerprints survive restarts (OpenSearch)
3. **High recall**: Hybrid approach catches exact and near-duplicates
4. **Scalable**: LSH provides sublinear query time
5. **Infrastructure reuse**: Documents already in OpenSearch
6. **Graceful degradation**: Works without Valkey cache; works without canonical URL (falls through to content)
7. **Incremental deployment**: Canonical URL dedup can ship independently before fingerprinting

### Negative

1. **OpenSearch load**: Additional indexing and queries
2. **Algorithm complexity**: Multiple fingerprint types to maintain
3. **Tuning required**: Shingle size, band count need calibration
4. **GMP dependency**: Additional PHP extension required

### Risks and Mitigations

| Risk                               | Mitigation                                                                |
| ---------------------------------- | ------------------------------------------------------------------------- |
| OpenSearch performance impact      | Fingerprint fields are keyword/integer, minimal overhead                  |
| False positives on templates       | Minimum word count threshold, exclude boilerplate                         |
| MinHash parameter sensitivity      | A/B testing, configurable per WatchFile                                   |
| GMP extension missing              | Docker image includes ext-gmp, CI validates                               |
| Canonical tag missing/wrong        | Falls through to content fingerprinting (stages 1-3)                      |
| Canonical URL extraction from HTML | Regex-based, may miss edge cases; acceptable tradeoff vs DOM parsing cost |

---

## Implementation Plan

| Step | Description                                  | Effort  |
| ---- | -------------------------------------------- | ------- |
| 1    | CanonicalUrlExtractor + enrichment processor | 1 day   |
| 2    | OpenSearch canonical_url field + dedup query | 0.5 day |
| 3    | Add ext-gmp to Docker PHP image              | 0.5 day |
| 4    | Core algorithms (normalizer, generators)     | 2 days  |
| 5    | OpenSearch fingerprint mapping + repository  | 1 day   |
| 6    | DuplicateDetector service (stages 0-3)       | 1 day   |
| 7    | Integration with quality pipeline (ADR-0027) | 1 day   |
| 8    | Valkey cache layer (optional)                | 1 day   |
| 9    | Calibration on sample dataset                | 2 days  |
| 10   | Load testing at target scale                 | 1 day   |

**Quick win**: Steps 1-2 (canonical URL dedup) can be deployed independently before content fingerprinting,
providing immediate value for the most common duplicate case.

---

## Related ADRs

- **ADR-0025**: Document Quality Pipeline Architecture
- **ADR-0027**: Document Quality Filters Phase 2 (DuplicateFilter integration)
