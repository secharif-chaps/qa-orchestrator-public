# ADR-2026-009: Provider-Agnostic Post-Collection Processing

| Status | Date       | Author                    |
| ------ | ---------- | ------------------------- |
| Draft  | 2026-02-06 | Frédéric Fayard-Le Barzic |

## Context

Target is adopting a multi-provider collection architecture (ADR-2026-008). Bakus, the current sole provider,
performs **default post-processing** on collected documents: PDF text extraction, content refinement, title/excerpt
enrichment, and CFC restriction classification. This happens automatically based on content type, independent of
the `postprocess_modules` API parameter.

A new collection provider may not offer equivalent post-processing. How does Target ensure consistent document
quality regardless of which provider collected the data?

### What Bakus Does by Default

| Capability               | Evidence                                   | Description                                       |
| ------------------------ | ------------------------------------------ | ------------------------------------------------- |
| Content refinement       | `document_refined_result` event type       | Sends a refined version alongside the raw version |
| PDF text extraction      | `content_type: 'application/pdf'` handling | Extracts text from PDFs, delivers as content      |
| Title/excerpt enrichment | Refined `title` and `excerpt` fields       | Better metadata than raw HTML `url_title`         |
| CFC restriction flagging | `cfc_restricted` field                     | Content classification for restricted material    |
| Raw/refined dual storage | `RESULT_TYPE_RAW` / `RESULT_TYPE_REFINED`  | Both versions fetchable separately                |

The code already handles this dual-delivery model: `MergedResultCollectDataHandler` processes raw results,
`handleRefinedResult()` processes enriched versions, and `AddDocumentHandler::mergeDocumentData()` merges them
(refined title/excerpt wins, longer content wins).

Additionally, `postprocess_modules` (currently `[]` in `BakusCollectTaskMapper`) could enable entity extraction,
automatic translation, and advanced NLP — available but not yet activated.

### The Gap With Other Providers

| Capability               | Bakus                     | Other Provider (example) |
| ------------------------ | ------------------------- | ------------------------ |
| Raw collection           | Yes                       | Yes                      |
| PDF text extraction      | Default                   | No                       |
| Content refinement       | Default                   | No                       |
| Title/excerpt enrichment | Default                   | Partial                  |
| CFC restriction          | Default                   | No                       |
| Entity extraction        | Available (not activated) | No                       |
| Translation              | Available (not activated) | No                       |

A document from another provider arrives **raw**: HTML content, URL, basic title. If Target relies on Bakus
post-processing for quality, switching providers degrades the analyst experience.

## Decision Drivers

- **Consistent document quality** regardless of collection provider
- **Leverage existing enrichment** when a provider delivers it (no redundant processing)
- **Reuse existing infrastructure**: LiteLLM for NLP, Processing Pipeline (ADR-2026-003) for scoring
- **Do not block multi-provider adoption** (ADR-2026-008) on post-processing readiness

## Considered Options

### Option A: Provider-Coupled Post-Processing

Each provider handles its own post-processing. Target consumes whatever quality the provider delivers.

**Problem**: Inconsistent quality. Same article via Bakus has enriched metadata; via another provider it has raw
HTML and generic title.

### Option B: Internal Post-Processing Pipeline (Selected)

Target internalizes critical post-processing as a provider-agnostic pipeline. When a provider delivers enriched
data, the pipeline detects it and skips redundant steps.

### Option C: Require All Providers to Match Bakus

Only accept providers offering equivalent post-processing.

**Problem**: Eliminates most alternatives. Defeats the purpose of reducing vendor lock-in.

## Decision

**Option B — Internal Post-Processing Pipeline with Provider Enrichment Pass-Through.**

### Rationale

1. **Provider enrichment is an optimization, not a dependency.** Bakus refinement improves quality, but Target must
   not depend on it. The bidirectional fallback in `getDocumentContent()` (raw ↔ refined) proves documents sometimes
   arrive without enrichment even from Bakus.

2. **LiteLLM is already available.** Entity extraction and translation can use the same LLM infrastructure as the
   AI agent, strategic questions, and document AI validation.

3. **The Processing Pipeline already exists (ADR-2026-003).** Post-processing steps fit naturally as
   `DocumentProcessorInterface` implementations with `supports()` skip logic.

4. **PDF extraction is commodity.** PHP libraries or extraction services handle this without a collection provider.

## Architecture

Enrichment processors are implemented as `DocumentProcessorInterface` implementations (ADR-2026-003) with high priority
(200+), ensuring they run **before** quality scoring processors (priority 50–100).

```
Document arrives from ANY provider
        ↓
AddDocumentHandler (existing — merges raw/refined if both arrive)
        ↓
Document Processing Pipeline (ADR-2026-003, async Messenger)
  ├── Phase 1: ENRICHMENT (priority 200+, this ADR)
  │   ├── PdfTextExtractionProcessor
  │   │   skip if: content is already readable text
  │   ├── TitleEnrichmentProcessor
  │   │   skip if: title is already enriched (not 'Untitled Document')
  │   ├── ExcerptGenerationProcessor
  │   │   skip if: excerpt differs from first 200 chars of content
  │   ├── EntityExtractionProcessor (LLM, future)
  │   │   skip if: entities already in metadata
  │   └── TranslationProcessor (LLM, future)
  │       skip if: content language matches WatchFile language
  │
  ├── Phase 2: QUALITY SCORING (priority 50-100, ADR-2026-004/005)
  │   └── Signal accumulation processors
  │
  └── Phase 3: ROUTING DECISION
      └── ACCEPTED / REVIEW / LOW_QUALITY
```

### Skip Logic

Each enrichment processor uses `supports()` to detect provider enrichment and skip redundant work:

```php
// Example: PdfTextExtractionProcessor implements DocumentProcessorInterface
public function supports(ProcessingContext $context): bool
{
    $doc = $context->document;
    if ($doc->getType() !== 'pdf') {
        return false;
    }
    // Skip if provider already extracted text
    return !$this->isReadableText($doc->getContent());
}

public function priority(): int
{
    return 250; // Enrichment phase — runs before scoring
}
```

For Bakus-delivered documents where `document_refined_result` has been merged, most enrichment processors skip. For raw
documents from other providers, all processors run. Same final quality for the analyst.

### Provider Contract

| Provider MUST Deliver                        | Provider MAY Optionally Deliver |
| -------------------------------------------- | ------------------------------- |
| Raw document content (HTML/text/PDF bytes)   | Pre-extracted text from PDFs    |
| Document URL                                 | Enriched title/excerpt          |
| Document title (or fallback to URL)          | Content classification          |
| Collection timestamp                         | Pre-extracted entities          |
| Provider-specific document ID (`providerId`) | Content language detection      |

### Capabilities Mapping

| Capability          | Current Owner     | Multi-Provider Owner | Implementation                      |
| ------------------- | ----------------- | -------------------- | ----------------------------------- |
| Raw collection      | Bakus             | Each provider        | Provider-specific                   |
| PDF text extraction | Bakus (default)   | Target internal      | Symfony service + PHP library       |
| Content refinement  | Bakus (default)   | Target internal      | Heuristic + LLM                     |
| CFC restriction     | Bakus (default)   | Bakus-only           | Not replicated (Bakus value-add)    |
| Entity extraction   | Bakus (available) | Target internal      | LLM via LiteLLM                     |
| Translation         | Bakus (available) | Target internal      | LLM via LiteLLM                     |
| Quality scoring     | N/A               | Target internal      | ADR-2026-003                        |
| AI validation       | Target (existing) | Target (unchanged)   | `TriggerDocumentAiValidationAction` |

## Implementation Roadmap

| #   | Action                                             | When                         | Size | Dependency                  |
| --- | -------------------------------------------------- | ---------------------------- | ---- | --------------------------- |
| 1   | Document minimal provider contract                 | Sprint 0 (with ADR-2026-008) | S    | ADR approval                |
| 2   | PDF text extraction as Symfony service             | Sprint 1                     | M    | PHP library selection       |
| 3   | Title/excerpt heuristic enrichment                 | Sprint 1                     | S    | —                           |
| 4   | Integrate into Processing Pipeline (ADR-2026-003)  | Sprint 2                     | M    | Processing Pipeline Phase 1 |
| 5   | Entity extraction via LLM (async Messenger)        | Sprint 2                     | M    | LiteLLM integration         |
| 6   | Translation via LLM (async Messenger)              | Sprint 2                     | M    | LiteLLM integration         |
| 7   | `supports()` skip logic for provider-enriched docs | Sprint 2                     | S    | Steps 2-6                   |

## Consequences

### Positive

- **Consistent quality**: every document reaches the same baseline regardless of provider
- **Provider contract simplifies**: providers only need to deliver raw content
- **Bakus enrichment not wasted**: skip logic preserves it when available
- **Reuses existing infra**: LiteLLM, Processing Pipeline, Messenger

### Negative

- **LLM cost**: entity extraction and translation per document adds API cost
- **Latency**: async pipeline adds processing delay before document is fully enriched
- **Complexity**: skip logic must correctly detect what the provider already did

### Neutral

- **No change to `AddDocumentHandler`**: merge logic continues for Bakus raw/refined flow
- **CFC restriction not replicated**: remains a Bakus-specific value-add

## References

- ADR-2026-003: Document Processing Pipeline Architecture
- ADR-2026-008: Multi-Provider Collection Architecture (companion ADR)
