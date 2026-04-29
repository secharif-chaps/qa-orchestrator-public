# ADR-2026-012: Implementation of Apify Provider for Data Collection

| Status   | Date       | Author       |
| -------- | ---------- | ------------ |
| Accepted | 2026-04-27 | Hajar Briere |

## Context

ADR-2026-008 defined the multi-provider architecture (Option B — Per-Provider Handler Packages + Tagged Service Locator) and laid the groundwork for adding a second collection provider. Apify was selected as the first secondary provider, initially targeting LinkedIn, Instagram, Google News, and website sources.

### Initial Scope (Phase 1)

Sources routed to Apify during initial implementation:

| SourceType                       | Apify Actor                              |
| -------------------------------- | ---------------------------------------- |
| `social_media:linkedin:user`     | `curious_coder/linkedin-profile-scraper` |
| `social_media:linkedin:company`  | `bebity/linkedin-premium-actor`          |
| `social_media:instagram:user`    | `apify/instagram-scraper`                |
| `social_media:instagram:hashtag` | `apify/instagram-scraper`                |
| `social_media:instagram:search`  | `apify/instagram-scraper`                |
| `news:newsapi`                   | `lhotanova/google-news-scraper`          |
| `news:newsapi:headlines`         | `lhotanova/google-news-scraper`          |
| `website`                        | `apify/website-content-crawler`          |
| `blog`                           | `apify/website-content-crawler`          |

### What Worked Well

The architecture defined in ADR-2026-008 delivered on its promises:

- The `ProviderGatewayLocator` resolved Apify without modifying the domain layer.
- The `SourceType → provider` routing via YAML enabled gradual activation source by source.
- Bakus and Apify coexist without any modifications to existing Bakus handlers.

### What Required Adaptation

Apify uses a **webhook-based asynchronous model** fundamentally different from Bakus's synchronous/streaming model:

1. **No real-time streaming**: Apify executes the actor, then notifies via webhook once the dataset is ready. There is no WebSocket stream. Bakus's Pull/Push pattern does not apply.
2. **Input per actor, not per SourceType**: each Apify actor has a different input schema. A YAML template system with variable interpolation was necessary.
3. **Normalization per actor**: the payload returned by each actor has a different structure. A normalizer system was introduced to produce a homogeneous `Document`.
4. **Cost tracking**: Apify bills on consumption (compute units). An `ApifyRunCost` Value Object and `source_collect_cost` `SourceActivity` were added to track costs per run.

---

## Decision

### Architecture Adopted

#### 1. `ApifyProviderGateway` — Command pattern (fire-and-forget)

`ApifyProviderGateway` implements `ProviderGatewayInterface`. The `createTask()` method submits the run to the Apify API and returns immediately without waiting for results. The `providerTaskId` encodes `{apifyActorId}:{runId}` to enable parsing when the webhook arrives.

```
CollectTask.providerTaskId = "apify/website-content-crawler:abc123run"
```

This convention is essential for linking the incoming webhook to the correct `CollectTask`.

#### 2. `ApifyCollectTaskMapper` — YAML templates

Actor inputs are defined in `config/services/collect_provider.yaml` under the `app.apify.input_templates` key. Each entry specifies:

- `source_types`: the supported SourceTypes
- `defaults`: input fields with their values (literal or interpolated)
- `required_fields`: fields whose absence raises an exception

```yaml
app.apify.input_templates:
  apify/website-content-crawler:
    source_types: ['website', 'blog']
    defaults:
      startUrls:
        - '{{source.url}}'
      maxCrawlPages: '{{config.maxCrawlPages|default:50}}'
    required_fields: ['startUrls']
```

#### 3. `ApifyInputInterpolator` — Variable resolution

Variables in templates are resolved at runtime:

| Variable                        | Source                                    |
| ------------------------------- | ----------------------------------------- |
| `{{source.url}}`                | `$source->getUrl()`                       |
| `{{source.query}}`              | `$source->getQuery()`                     |
| `{{config.KEY}}`                | `$source->getParameter('KEY')`            |
| `{{config.KEY\|default:VALUE}}` | `$source->getParameter('KEY')` or `VALUE` |

#### 4. Async webhook pattern

The complete Apify collection flow:

```
ApifyProviderGateway::createTask()
    └── POST /v2/acts/{actorId}/runs   → Apify launches the run
            │
            │ (run completed)
            ▼
    Apify POST webhook → ApifyWebhookController
            │
            └── Dispatch FetchApifyDatasetAction → message bus
                    │
                    └── FetchApifyDatasetHandler
                            ├── GET /v2/datasets/{datasetId}/items
                            ├── Normalization via ApifyDocumentNormalizerInterface
                            ├── Dispatch AddDocumentAction per item
                            └── Log ApifyRunCost in SourceActivity
```

#### 5. Normalizers per actor

Each actor returns its own payload format. A normalizer system isolates this mapping:

| Normalizer                  | Actor(s)                                 |
| --------------------------- | ---------------------------------------- |
| `GoogleNewsNormalizer`      | `lhotanova/google-news-scraper`          |
| `WebsiteCrawlerNormalizer`  | `apify/website-content-crawler`          |
| `LinkedInProfileNormalizer` | `curious_coder/linkedin-profile-scraper` |
| `GenericApifyNormalizer`    | fallback for any unmapped actor          |

Normalizers implement `ApifyDocumentNormalizerInterface` and are registered automatically via `autoconfigure: true`. `GenericApifyNormalizer` serves as a safety net: if no normalizer claims the actor, it produces a minimal document rather than failing the run.

#### 6. `ApifyRunCost` — Cost Value Object

```php
readonly class ApifyRunCost
{
    public function __construct(
        public float $computeUnits,
        public float $costUsd,
    ) {}
}
```

The cost is tracked in `SourceActivity` with `action_type = 'source_collect_cost'` and structured metadata (`compute_units`, `cost_usd`, `apify_actor_id`, `run_id`).

#### 7. DI Registration

```yaml
# config/services/collect_provider.yaml
App\Infrastructure\Collect\Apify\ApifyProviderGateway:
  tags:
    - { name: 'app.collect.provider', key: 'apify' }
```

---

## Consequences

### Positive

- **Decoupled from Apify webhook format**: `ApifyRunCost` is a pure domain VO; parsing the webhook format lives in the Controller.
- **Extensible without code changes**: Adding a new actor requires only YAML configuration + a normalizer, no changes to the core handler.
- **Cost visibility**: Every Apify run is tracked in `source_activities`, enabling cost analysis per actor, per source, per day.
- **Deterministic**: Webhook ordering and race conditions are handled by Symfony Messenger's transactional dispatch.

### Tradeoffs

- **Async latency**: Documents appear in the system only after the webhook is received, not in real-time. Typical latency: seconds to minutes depending on Apify.
- **Webhook reliability**: If the webhook endpoint is unreachable, Apify retries; if all retries fail, the run is marked as "webhook failed" in Apify UI but the CollectTask remains "pending". Manual intervention required (rare).
- **Cost budget**: `APIFY_MAX_TOTAL_CHARGE_USD` is a coarse safeguard. For fine-grained cost control, adjust actor templates or routing per SourceType.

---

## Implementation Details

### Testing Strategy

- **Unit tests**: Normalizers (9-12 tests each), Action handlers, webhook payload parsing.
- **Integration tests**: Provider locator resolution, DI container wiring, `SourceType → Apify` routing consistency, Bakus non-regression.
- **Manual testing**: On staging; Apify webhook cannot reach localhost.

### Documentation

- **ADR-2026-012** (this file): Architecture and decision rationale.
- **apify-add-new-source.md**: Step-by-step guide for adding a new Apify actor (8 steps, YAML-only after normalizer is written).
- **apify-operations.md**: Operational guide for cost monitoring, troubleshooting, and log analysis.

### Future Phases

- **Phase 2/3**: Additional normalizers and templates via YAML only (no code changes).
- **Cost optimization**: Analyze expensive runs and optimize actor templates or switch SourceTypes to Bakus if Apify costs exceed ROI.
- **Webhook resilience**: Implement webhook signature validation (HMAC) when Apify provides it.

---

## References

- ADR-2026-008: Multi-Provider Architecture
- `config/services/collect_provider.yaml`: Apify configuration
- `src/Infrastructure/Collect/Apify/`: Apify implementation
- `tests/Units/Infrastructure/Collect/Apify/Normalizer/`: Normalizer tests
- `tests/Integration/Collect/`: Integration tests (provider locator, DI wiring, routing consistency)
