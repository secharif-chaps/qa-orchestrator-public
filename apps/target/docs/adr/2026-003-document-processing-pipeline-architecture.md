# ADR-2026-003: Document Processing Pipeline Architecture

| Status   | Date       | Author                    |
| -------- | ---------- | ------------------------- |
| Accepted | 2026-01-11 | Frédéric Fayard-Le Barzic |

## Context

Target collects documents from various sources (web crawlers, RSS feeds, APIs) for business intelligence monitoring. The
current system processes all collected documents equally, leading to two problems:

### Quality Problem

1. **High noise ratio**: During initial collection for a new WatchFile (especially web crawling), 30-50% of documents
   are irrelevant (navigation pages, login forms, legal notices, etc.)

2. **No quality visibility**: Users cannot understand why certain documents appear or how relevant they are

3. **Wasted resources**: All documents go through full indexation in Elasticsearch, AI analysis, and storage regardless
   of quality

4. **Scale concerns**: Target of 50M documents/year (~140k docs/day) makes noise reduction critical for cost control

### Enrichment Problem (ADR-2026-008, ADR-2026-009)

5. **Provider-dependent enrichment**: Bakus performs default post-processing (PDF text extraction, content refinement,
   title/excerpt enrichment) but other collection providers may not. Document quality varies by provider.

6. **No internal enrichment capability**: Target has no post-collection enrichment pipeline for PDF extraction, title
   improvement, entity extraction, or translation — it relies entirely on Bakus for these capabilities.

### Requirements

1. **Transparent scoring**: Users must see quality scores and understand why documents are rated as they are
2. **Configurable thresholds**: Each WatchFile can define its own acceptance criteria
3. **Traceability**: All processing decisions must be logged with reasons, even for rejected documents
4. **Extensibility**: Easy to add new processors (enrichment, heuristics, ML models, external APIs) over time
5. **Performance**: Pipeline must handle 140k docs/day with minimal latency impact
6. **Provider-agnostic enrichment**: Documents reach the same quality baseline regardless of collection provider

## Decision Drivers

- Favor **Option B from reflexion**: Permissive scoring with visible signals, not aggressive filtering with whitelists
- Client knows best what's relevant for their specific monitoring needs
- Scoring data enables future ML training via feedback loop
- Must integrate with existing Symfony Messenger async processing
- Persistence of quality reports for analytics and audit
- Pipeline must handle BOTH enrichment and quality scoring (not just filtering)

## Considered Options

### Option A: Chain of Responsibility Pattern

Each processor either handles the document (accept/reject) or passes to next handler.

```php
interface DocumentHandler {
    public function handle(Document $doc): ?Decision;
    public function setNext(DocumentHandler $handler): void;
}
```

**Pros:**

- Simple, well-known pattern
- Early exit on first decisive handler

**Cons:**

- Only one handler acts per document
- Cannot accumulate scores from multiple signals
- Poor fit for "scoring" use case where all signals contribute
- No support for enrichment (only routing decisions)

### Option B: Pipeline Pattern with Signal Accumulation (Selected)

All processors contribute to a shared context. Enrichment processors modify the document. Scoring processors add
signals. Final score is computed from accumulated signals.

```php
interface DocumentProcessorInterface {
    public function process(ProcessingContext $context): ProcessingContext;
    public function supports(ProcessingContext $context): bool;
    public function priority(): int;
}
```

**Pros:**

- Unified interface for enrichment AND quality scoring
- All scoring processors contribute signals grouped by category
- Final score = min(category averages) — a weak category cannot be hidden by strong ones
- Enrichment processors can skip via `supports()` when provider already enriched the document
- Early exit still possible (e.g., blocked domain)
- Transparent: each signal explains its contribution, category scores visible
- Weights configurable per WatchFile

**Cons:**

- Slightly more complex than Chain of Responsibility
- All processors run even if early decision could be made (mitigated by `supports()` check)

### Option C: Event-Driven with Parallel Processors

Fan-out to parallel processor workers, fan-in to aggregator.

**Pros:**

- Maximum parallelization
- Scales independently per processor type

**Cons:**

- High complexity (correlation IDs, aggregator state, timeout handling)
- Overkill for 140k docs/day
- Debugging difficulty

## Decision

**Option B: Pipeline Pattern with Signal Accumulation**

The pipeline pattern supports both enrichment and scoring in a single, ordered execution model. Each processor uses
`supports()` to decide whether it should run (enabling skip logic for provider-enriched documents) and `priority()`
to control execution order.

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    DOCUMENT PROCESSING PIPELINE                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Phase 1: ENRICHMENT (priority 200+)                                 │
│  ┌──────────────────────────────────────────────────────────────┐    │
│  │  PdfTextExtraction → TitleEnrichment → ExcerptGeneration    │    │
│  │  skip if: provider already enriched (Bakus refined result)  │    │
│  └──────────────────────────────────────────────────────────────┘    │
│                              ↓                                       │
│  Phase 2: QUALITY SCORING (priority 50-100)                          │
│  ┌──────────────────────────────────────────────────────────────┐    │
│  │  WatchFileOverride → TldReputation → HTTPS → Adblock → ...  │    │
│  │  Each adds a weighted Signal to ProcessingContext             │    │
│  └──────────────────────────────────────────────────────────────┘    │
│                              ↓                                       │
│  Phase 3: ROUTING DECISION                                           │
│  ┌──────────────────────────────────────────────────────────────┐    │
│  │  Score ≥ autoAcceptThreshold  →  ACCEPTED   → Full indexation│    │
│  │  Score ∈ [autoReject, autoAccept) → REVIEW  → + review badge │    │
│  │  Score < autoRejectThreshold  →  LOW_QUALITY → Minimal store │    │
│  └──────────────────────────────────────────────────────────────┘    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Processing Phases

The pipeline runs processors in **descending priority order**. Priority ranges define phases:

| Phase               | Priority Range | Purpose                                 | Processors                                                               |
| ------------------- | -------------- | --------------------------------------- | ------------------------------------------------------------------------ |
| 1 — Enrichment      | 200+           | Improve document content before scoring | PDF extraction, title/excerpt enrichment, entity extraction, translation |
| 2 — Quality Scoring | 50–100         | Accumulate quality signals              | TLD reputation, HTTPS, word count, content ratio, URL patterns, etc.     |
| 3 — Routing         | 0              | Compute final score and route           | Built into pipeline orchestrator                                         |

**Enrichment processors** modify the document (set better title, extract PDF text) and may add signals. They use
`supports()` to skip when the provider already enriched the document — e.g., Bakus's `document_refined_result`
already provides title and excerpt, so `TitleEnrichmentProcessor` skips.

**Scoring processors** read document data and add weighted signals to the context. They do not modify the document.

Both implement the same `DocumentProcessorInterface`.

### Domain Model

#### SignalCategory Enum

```php
enum SignalCategory: string
{
    case INFRASTRUCTURE_TRUST = 'infrastructure_trust'; // TLD, HTTPS, adblock, domain age
    case CONTENT_QUALITY = 'content_quality';           // Word count, content ratio, duplicates
    case SOURCE_CREDIBILITY = 'source_credibility';     // Source reputation, social proof
    case METADATA = 'metadata';                         // URL patterns, publication date
}
```

Each scoring processor declares the category its signal belongs to. The final score is computed as the
**minimum of category averages** — a weak category cannot be compensated by strong categories.

#### Signal Value Object

```php
final readonly class Signal
{
    public function __construct(
        public float $value,                // 0.0 - 1.0 normalized score
        public float $weight,               // Contribution weight within category
        public SignalCategory $category,    // Signal category
        public ?string $reason,             // Human-readable explanation
    ) {}

    public function contribution(): float
    {
        return $this->value * $this->weight;
    }
}
```

#### ProcessingContext (Immutable)

```php
final readonly class ProcessingContext
{
    public function __construct(
        public Document $document,
        public WatchFile $watchFile,
        public array $signals = [],                    // ['signal_name' => Signal]
        public ?QualityDecision $earlyDecision = null, // For immediate reject/accept
        public ?string $earlyDecisionReason = null,
    ) {}

    public function withSignal(string $name, Signal $signal): self;
    public function withEarlyDecision(QualityDecision $decision, string $reason): self;
    public function hasEarlyDecision(): bool;
}
```

Note: The context is immutable for signal accumulation, but enrichment processors modify the `Document` entity directly
(it's a mutable Elasticsearch entity passed by reference). This is by design — enrichment changes the document,
scoring reads it.

#### QualityDecision Enum

```php
enum QualityDecision: string
{
    case ACCEPTED = 'accepted';         // Score >= autoAcceptThreshold
    case REVIEW = 'review';             // Score between thresholds
    case LOW_QUALITY = 'low_quality';   // Score < autoRejectThreshold
    case REJECTED = 'rejected';         // Early decision (blocked domain, duplicate, etc.)
}
```

#### QualityReport Entity (Persisted)

```php
final readonly class QualityReport
{
    public function __construct(
        public DocumentId $documentId,
        public float $overallScore,           // 0.0 - 1.0 = min(category scores)
        public array $categoryScores,         // ['infrastructure_trust' => 0.42, ...]
        public array $signals,                // All contributing signals
        public QualityDecision $decision,
        public ?string $decisionReason,
        public \DateTimeImmutable $computedAt,
    ) {}
}
```

#### DocumentProcessorInterface

```php
interface DocumentProcessorInterface
{
    /**
     * Process document: enrich content and/or add quality signal(s) to context.
     * Enrichment processors modify the document directly.
     * Scoring processors add signals via $context->withSignal().
     * May set early decision for immediate routing.
     */
    public function process(ProcessingContext $context): ProcessingContext;

    /**
     * Check if this processor should run for given context.
     * Enrichment processors: skip if provider already enriched the document.
     * Scoring processors: skip based on document type, early decision, etc.
     */
    public function supports(ProcessingContext $context): bool;

    /**
     * Execution order. Higher = runs first.
     * Enrichment processors: 200+ (run first, before scoring).
     * Scoring processors: 50-100 (run on enriched document).
     */
    public function priority(): int;
}
```

### WatchFile Quality Configuration

Each WatchFile can customize quality thresholds and processing behavior:

```php
final readonly class QualityConfig
{
    public function __construct(
        public float $autoAcceptThreshold = 0.7,    // Score >= this → ACCEPTED
        public float $autoRejectThreshold = 0.2,    // Score < this → LOW_QUALITY
        public bool $showReviewQueue = true,        // Display review items to user
        public array $trustedDomains = [],          // Override: always high score
        public array $blockedDomains = [],          // Override: immediate reject
        public array $signalWeightOverrides = [],   // Custom weights per signal
    ) {}
}
```

### Pipeline Orchestrator

```php
final class DocumentProcessingPipeline
{
    /** @param iterable<DocumentProcessorInterface> $processors - Auto-sorted by priority */
    public function __construct(
        private iterable $processors,
    ) {}

    public function process(Document $document, WatchFile $watchFile): QualityReport
    {
        $context = new ProcessingContext($document, $watchFile);

        foreach ($this->processors as $processor) {
            if (!$processor->supports($context)) {
                continue;
            }

            $context = $processor->process($context);

            // Early exit if decision made (blocked domain, duplicate, etc.)
            if ($context->hasEarlyDecision()) {
                break;
            }
        }

        return $this->buildReport($context);
    }

    private function buildReport(ProcessingContext $context): QualityReport
    {
        if ($context->earlyDecision === QualityDecision::REJECTED) {
            return new QualityReport(
                documentId: $context->document->getId(),
                overallScore: 0.0,
                categoryScores: [],
                signals: $context->signals,
                decision: QualityDecision::REJECTED,
                decisionReason: $context->earlyDecisionReason,
            );
        }

        // Group signals by category
        $categoryScores = $this->computeCategoryScores($context->signals);

        // Final score = minimum of all category scores
        // A weak category cannot be compensated by strong categories
        $overallScore = empty($categoryScores) ? 0.0 : min($categoryScores);

        $decision = $this->determineDecision($overallScore, $context->watchFile);

        return new QualityReport(
            documentId: $context->document->getId(),
            overallScore: $overallScore,
            categoryScores: $categoryScores,
            signals: $context->signals,
            decision: $decision,
        );
    }

    /**
     * Compute weighted average score per category.
     *
     * @param array<string, Signal> $signals
     * @return array<string, float> Category name => score (0.0 - 1.0)
     */
    private function computeCategoryScores(array $signals): array
    {
        $categories = []; // category => ['totalScore' => float, 'totalWeight' => float]

        foreach ($signals as $signal) {
            $cat = $signal->category->value;

            if (!isset($categories[$cat])) {
                $categories[$cat] = ['totalScore' => 0.0, 'totalWeight' => 0.0];
            }

            $categories[$cat]['totalScore'] += $signal->contribution();
            $categories[$cat]['totalWeight'] += $signal->weight;
        }

        $result = [];
        foreach ($categories as $cat => $data) {
            $result[$cat] = $data['totalWeight'] > 0
                ? $data['totalScore'] / $data['totalWeight']
                : 0.0;
        }

        return $result;
    }

    private function determineDecision(float $score, WatchFile $wf): QualityDecision
    {
        $config = $wf->getQualityConfig();

        return match (true) {
            $score >= $config->autoAcceptThreshold => QualityDecision::ACCEPTED,
            $score < $config->autoRejectThreshold => QualityDecision::LOW_QUALITY,
            default => QualityDecision::REVIEW,
        };
    }
}
```

### Symfony Integration

#### Service Configuration

```yaml
# config/services.yaml
services:
  # Auto-register all processors with priority sorting
  App\Infrastructure\DocumentQuality\Processor\:
    resource: '../src/Infrastructure/DocumentQuality/Processor/'
    tags: ['app.document_processor']

  App\Application\DocumentQuality\DocumentProcessingPipeline:
    arguments:
      $processors: !tagged_iterator
        tag: 'app.document_processor'
        default_priority_method: 'priority'
```

#### Messenger Integration

```php
// Message dispatched after document collection
final readonly class ProcessDocumentQuality implements AsyncMessageInterface
{
    public function __construct(
        public string $documentId,
        public string $watchFileId,
    ) {}
}

#[AsMessageHandler]
final readonly class ProcessDocumentQualityHandler
{
    public function __construct(
        private DocumentGateway $documents,
        private WatchFileGateway $watchFiles,
        private DocumentProcessingPipeline $pipeline,
        private QualityReportGateway $reports,
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(ProcessDocumentQuality $message): void
    {
        $document = $this->documents->get(new DocumentId($message->documentId));
        $watchFile = $this->watchFiles->get(new WatchFileId($message->watchFileId));

        $report = $this->pipeline->process($document, $watchFile);

        $this->reports->save($report);

        // Dispatch event for downstream processing (indexation, notifications)
        $this->bus->dispatch(new DocumentQualityProcessed(
            $message->documentId,
            $report->overallScore,
            $report->decision->value,
        ));
    }
}
```

#### Messenger Transport Configuration

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      quality_processing:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options:
          queues:
            quality_processing:
              binding_keys: [quality]
        retry_strategy:
          max_retries: 3
          delay: 1000
          multiplier: 2

    routing:
      'App\Application\DocumentQuality\Message\ProcessDocumentQuality': quality_processing
```

### Directory Structure

Following Target's domain-centric organization:

```
api/src/
├── Domain/
│   └── DocumentQuality/
│       ├── Signal.php                           # Value object
│       ├── ProcessingContext.php                 # Immutable context
│       ├── QualityReport.php                    # Entity
│       ├── QualityDecision.php                  # Enum
│       ├── QualityConfig.php                    # Value object (WatchFile config)
│       ├── DocumentProcessorInterface.php       # Processor contract
│       ├── QualityReportGatewayInterface.php
│       └── Exception/
│           └── QualityReportNotFoundException.php
│
├── Application/
│   └── DocumentQuality/
│       ├── ProcessDocumentQuality/
│       │   ├── ProcessDocumentQualityAction.php
│       │   └── ProcessDocumentQualityHandler.php
│       ├── DocumentProcessingPipeline.php       # Pipeline orchestrator
│       └── Event/
│           └── DocumentQualityProcessedEvent.php
│
└── Infrastructure/
    └── DocumentQuality/
        ├── Processor/
        │   ├── Enrichment/                      # Phase 1: Enrichment (priority 200+)
        │   │   ├── PdfTextExtractionProcessor.php
        │   │   ├── TitleEnrichmentProcessor.php
        │   │   └── ExcerptGenerationProcessor.php
        │   │
        │   └── Scoring/                         # Phase 2: Quality Scoring (priority 50-100)
        │       ├── WatchFileOverrideProcessor.php
        │       ├── TldReputationProcessor.php
        │       ├── HttpsProcessor.php
        │       ├── AdblockDomainProcessor.php
        │       ├── WordCountProcessor.php
        │       ├── ContentRatioProcessor.php
        │       ├── UrlPatternProcessor.php
        │       └── PublicationDateProcessor.php
        │
        ├── QualityReportDoctrineGateway.php
        └── Service/
            └── SourceReputationProvider.php
```

### Database Schema

```sql
CREATE TABLE quality_report
(
    id              UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    document_id     UUID                     NOT NULL REFERENCES document (id) ON DELETE CASCADE,
    overall_score   DECIMAL(5, 4)            NOT NULL, -- 0.0000 to 1.0000
    decision        VARCHAR(20)              NOT NULL, -- accepted, review, low_quality, rejected
    decision_reason TEXT,
    signals         JSONB                    NOT NULL, -- Full signal breakdown
    computed_at     TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),

    CONSTRAINT quality_report_document_unique UNIQUE (document_id)
);

CREATE INDEX idx_quality_report_decision ON quality_report (decision);
CREATE INDEX idx_quality_report_score ON quality_report (overall_score);
CREATE INDEX idx_quality_report_computed_at ON quality_report (computed_at);
```

## Consequences

### Positive

1. **Transparent scoring**: Users understand why documents are rated, can adjust thresholds
2. **Extensible**: New processors added by implementing interface + tagging service
3. **Testable**: Each processor unit-testable, pipeline integration-testable
4. **Audit trail**: All decisions persisted with full signal breakdown
5. **Client control**: WatchFile-level configuration respects "client knows best" principle
6. **Feedback ready**: Signal data enables future ML training from user feedback
7. **Unified pipeline**: Enrichment and scoring in a single, ordered execution model
8. **Provider-agnostic**: Enrichment processors ensure consistent quality regardless of collection provider

### Negative

1. **Storage overhead**: QualityReport for every document (~500 bytes JSON per doc)
2. **Pipeline latency**: Sequential processor execution adds processing time
3. **Complexity**: More moving parts than simple accept/reject logic
4. **LLM cost**: Future enrichment processors (entity extraction, translation) add API cost per document

### Risks and Mitigations

| Risk                         | Mitigation                                                     |
| ---------------------------- | -------------------------------------------------------------- |
| Processor execution too slow | Priority ordering: cheap processors first, expensive last      |
| Score weights hard to tune   | Category-based min ensures a weak dimension is never hidden    |
| Too many documents in REVIEW | Configurable thresholds per WatchFile, can narrow band         |
| Database bloat from reports  | TTL policy: archive reports older than 90 days to cold storage |
| Enrichment skip logic errors | Each enrichment processor documents its skip criteria          |

## Implementation Plan

| Phase | Scope                                       | Effort   |
| ----- | ------------------------------------------- | -------- |
| 1     | Domain model + Pipeline + Messenger setup   | 3-4 days |
| 2     | Database schema + Gateway implementation    | 2 days   |
| 3     | Scoring processors (see ADR-2026-004)       | 3-4 days |
| 4     | Enrichment processors (see ADR-2026-009)    | 3-4 days |
| 5     | WatchFile config UI integration             | 2-3 days |
| 6     | Document list UI with quality badges/scores | 2-3 days |

## Related ADRs

- **ADR-2026-004**: Document Quality Scoring Processors Phase 1 (concrete scoring implementations)
- **ADR-2026-005**: Document Quality Scoring Processors Phase 2 (external data integrations)
- **ADR-2026-008**: Multi-Provider Collection Architecture (provider contract and enrichment needs)
- **ADR-2026-009**: Provider-Agnostic Post-Collection Processing (enrichment processor details, skip logic)
- **Future**: Legacy validation data integration for source trust scoring
- **Future**: ML-based classification processors
