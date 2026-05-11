# ADR-2026-011: Split Document Pipeline into Pre-Save and Post-Save Phases

| Status   | Date       | Author                    |
| -------- | ---------- | ------------------------- |
| Accepted | 2026-04-28 | Frédéric Fayard-Le Barzic |

## Context

ADR-2026-003 introduced a single `DocumentProcessingPipeline` that ran _after_ `documentGateway->save()` and produced a `QualityReport`. ADR-2026-006 (deduplication) added the need for **enrichment** (canonical URL, fingerprints) and **exact-match deduplication** to happen _before_ save — otherwise the document is indexed without its enrichments and a duplicate is persisted unnecessarily.

The original design also coupled three concerns inside one orchestration:

1. running processors over a `Document`,
2. interpreting halt as `QualityDecision::REJECTED`,
3. assembling a `QualityReport`.

That coupling blocked reuse for non-quality concerns (deduplication, future analyses) and forced every new processor to live under `Domain/DocumentQuality/` regardless of intent.

## Decision

### 1. Two pipelines, one implementation, two DI tags

A single neutral `DocumentPipeline` class is instantiated twice:

- `app.document_pipeline.pre_save` — runs **synchronously** inside `IngestDocumentHandler` before the document is persisted. Picks processors tagged `app.document_pre_save_processor` (marker interface `PreSaveDocumentProcessorInterface`).
- `app.document_pipeline.post_save` — runs **asynchronously** inside `ProcessDocumentQualityHandler` after the document is persisted. Picks processors tagged `app.document_post_save_processor` (marker interface `PostSaveDocumentProcessorInterface`).

Each pipeline orders its processors by `#[AsTaggedItem(priority: …)]`. The informative `PipelinePhase` enum names the conventional priority ranges:

| Phase           | Range   | Pipeline                                                          |
| --------------- | ------- | ----------------------------------------------------------------- |
| `ENRICHMENT`    | 200-299 | pre-save                                                          |
| `DEDUPLICATION` | 100-199 | pre-save (Stage 0 — exact match) and post-save (Stage 1+ — fuzzy) |
| `SCORING`       | 1-99    | post-save                                                         |

### 2. Neutral pipeline returns a neutral context

`DocumentPipeline::process()` returns a `DocumentPipelineContext` (no domain artefact). The context carries:

- `signals` — accumulated `Signal`s (filled by scoring processors);
- `isHalted` / `haltReason` — neutral stop flag (replaces the old `?QualityDecision $earlyDecision`);
- `duplicateOf` — set by deduplication processors to short-circuit the save in the ingest handler.

A new `QualityReportBuilder` interprets that context: a halted context becomes `QualityDecision::REJECTED`; otherwise the configured `ScoringStrategy` produces the overall score and final decision.

### 3. `IngestDocumentAction` (formerly `AddDocumentAction`)

The unified ingestion message is renamed to make its role explicit. Both the manual API processor (`CreateDocumentProcessor`) and the CLI command (`document:create`) build a `Document` (now via the shared `DocumentBuilderFromHtmlMetadata`) and dispatch `IngestDocumentAction`. So do every collect provider (Apify, Bakus). The `Create*` symbols cover _initial creation from a URL or raw HTML_; the `Ingest*` symbols cover the unified post-construction step.

### 4. Namespace layout

```
Domain/Document/Pipeline/                  — orchestration, neutral
  DocumentPipelineInterface.php
  DocumentProcessorInterface.php
  PreSaveDocumentProcessorInterface.php
  PostSaveDocumentProcessorInterface.php
  DocumentPipelineContext.php
  PipelinePhase.php

Domain/DocumentQuality/                    — quality concepts only
  QualityReport.php / QualityReportBuilder.php
  Signal.php / SignalCategory.php
  ScoringStrategy.php / DefaultScoringStrategy.php / ScoringResult.php
  QualityDecision.php / QualityConfig.php

Infrastructure/Document/Pipeline/
  DocumentPipeline.php
  Processor/
    Enrichment/    — pre-save
    Deduplication/ — pre-save (Stage 0) and post-save (Stage 1+)
    Scoring/       — post-save (5 existing processors live here)
```

## Consequences

### Positive

- **Pipeline reusable** for any concern that needs to walk processors over a document. Future deduplication, signalisation, analysis features add processors without touching the orchestrator.
- **Pre-save enrichments propagate to OpenSearch.** Before this change, the pipeline ran after save, so any field a processor wrote on the `Document` was lost.
- **Exact-duplicate detection avoids unnecessary writes** to OpenSearch (the pre-save handler returns early when `duplicateOf` is set).
- **Domain/DocumentQuality** is reduced to pure quality artefacts. `QualityReportBuilder` holds the only bridge between the neutral pipeline and the quality domain.
- **Naming reads honestly:** `IngestDocumentHandler` is the entrypoint, `DocumentPipeline` is the orchestrator, `Pre/PostSave…ProcessorInterface` are the markers, `QualityReport*` is quality.

### Negative

- **Two DI services** instead of one — slightly more configuration, mitigated by the shared implementation class.
- **Pre-save pipeline runs synchronously**, adding latency to the ingest critical path. Pre-save processors must remain fast (regex/hash, no I/O); the bigger work stays post-save and async.
- **Marker interfaces** (`PreSave…` / `PostSave…`) require concrete processors to choose their phase explicitly. This is the trade-off for two distinct DI tags; the alternative (a single tag with runtime filtering) was discarded as harder to reason about.

### Neutral

- **`Document` is unchanged** by the refactor itself. Pre-save enrichments mutate the same `Document` instance the handler holds, so the indexed payload reflects what the pipeline produced.
- **The async `ProcessDocumentQualityAction`** is preserved and dispatched after the synchronous save commit (`DispatchAfterCurrentBusStamp`).

## Related ADRs

- ADR-2026-003 — Document Processing Pipeline Architecture (this ADR is an evolution).
- ADR-2026-006 — Document Deduplication Strategy (Stage 0 storage relies on the pre-save phase introduced here).
- ADR-2026-009 — Provider-Agnostic Post-Collection Processing (the `IngestDocumentAction` unification was foreshadowed here).
