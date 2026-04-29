# Adding a New Apify Source

This guide describes the steps to integrate a new Apify actor into the collection pipeline. The goal is to stay within YAML + normalizer scope without touching core business logic.

**Prerequisites**: Have read [ADR-2026-012](../adr/2026-012-apify-provider-implementation.md) and have access to an Apify account with test credits.

---

## Step 1: Identify the Apify Actor

1. Go to [https://apify.com/store](https://apify.com/store) and search by use case (e.g., "Twitter scraper", "YouTube scraper").
2. Prefer actors from the `apify/` organization (officially maintained) or actors with a large run history.
3. Manually test the actor via the Apify UI to validate:
   - The expected input schema
   - The output dataset schema (structure of items)
   - The approximate cost per run (compute units)
4. Note the **actor ID** in the format `owner/actor-name` (visible in the actor page URL).

---

## Step 2: Add the SourceType (if new source)

If the source doesn't yet exist in the `SourceType` enum, add it.

File: `src/Domain/Source/SourceType.php`

```php
enum SourceType: string
{
    // ... existing cases ...
    case MY_NEW_SOURCE = 'my:new:source'; // kebab-case descriptive value
}
```

Naming convention for enum values:

- Use `:` as a hierarchical separator: `social_media:twitter:user`
- No uppercase, no dashes in the enum value
- The PHP `case` is in `SCREAMING_SNAKE_CASE`

If the SourceType already exists (e.g., only changing the Apify actor used), skip to step 3.

---

## Step 3: Configure the Actor Mapping

Declare the `SourceType → Apify actor` correspondence in the configuration file.

File: `config/services/collect_provider.yaml`

```yaml
parameters:
  app.apify.actor_mapping:
    # ... existing mappings ...
    my:new:source: 'owner/actor-name'
```

A single actor can cover multiple SourceTypes:

```yaml
my:source:variant_a: 'owner/actor-name'
my:source:variant_b: 'owner/actor-name'
```

---

## Step 4: Add the Input Template

Each Apify actor has its own input schema. The template defines how to build this input from source data.

File: `config/services/collect_provider.yaml`

```yaml
parameters:
  app.apify.input_templates:
    # ... existing templates ...

    owner/actor-name:
      name: 'Readable name of the actor'
      source_types: ['my:new:source']
      defaults:
        # Fields of the Apify input, with interpolated variables or fixed values
        query: '{{source.query}}'
        startUrls:
          - '{{source.url}}'
        maxResults: '{{config.maxResults|default:50}}'
        language: '{{config.language|default:en}}'
        proxyConfiguration:
          useApifyProxy: true
      required_fields: ['query']
```

### Available Variables in Templates

| Variable                        | Resolution                               | Example                    |
| ------------------------------- | ---------------------------------------- | -------------------------- |
| `{{source.url}}`                | Source URL (`$source->getUrl()`)         | `https://example.com`      |
| `{{source.query}}`              | Source query (`$source->getQuery()`)     | `artificial intelligence`  |
| `{{config.KEY}}`                | Source parameter (`getParameter('KEY')`) | configured source value    |
| `{{config.KEY\|default:VALUE}}` | Parameter or default value if absent     | `50` if KEY not configured |

### `required_fields`

If a field listed in `required_fields` is empty after interpolation (e.g., `{{source.url}}` on a source without a URL), creating the Apify run fails with a clear exception rather than submitting invalid input.

---

## Step 5: Create the Normalizer

The normalizer transforms an Apify dataset item into a `Document` in the domain.

File: `src/Infrastructure/Collect/Apify/Normalizer/MyNewSourceNormalizer.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Document\Document;

// The normalizer is readonly and uses ApifyNormalizerTrait for shared helpers.
readonly class MyNewSourceNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;

    // Returns true if this normalizer handles the given actor.
    public function supports(string $actorId): bool
    {
        return $actorId === 'owner/actor-name';
    }

    // Returns null if the item is invalid/incomplete (will be silently ignored).
    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        // Validate required fields
        $url = $this->extractString($item, ['url']);
        $title = $this->buildTitle($item, ['title']);

        if (null === $url || '' === $url) {
            return null;
        }

        $excerpt = $this->buildExcerpt($item['text'] ?? $item['description'] ?? '', $title);
        if (null === $excerpt) {
            return null;
        }

        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['published_date', 'date']) ?? $dateCollect;

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $item['text'] ?? $item['description'] ?? $title,
            url: $url,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', 'owner/actor-name', $url));

        return $document;
    }
}
```

### Important Rules

- The class must be `readonly`.
- Use `use ApifyNormalizerTrait` to access helpers (`parseDate()`, `buildTitle()`, `buildExcerpt()`, `extractString()`, etc.).
- `supports()` must be strictly exclusive: one normalizer per actor (unless multiple actors share the same format).
- Return `null` for invalid items rather than throwing an exception: the item will be ignored and the run continues.
- If no normalizer claims the actor, `GenericApifyNormalizer` automatically takes over (fallback).

### Automatic Registration

Thanks to `autoconfigure: true` in `services.yaml`, any class implementing `ApifyDocumentNormalizerInterface` is automatically tagged and injected into the normalization chain. No manual service declaration is needed.

---

## Step 6: Configure the Routing

Declare that this SourceType should be handled by the Apify provider.

File: `config/services/collect_provider.yaml`

```yaml
parameters:
  app.collect.provider_routing.source_types:
    # ... existing routings ...
    my:new:source: 'apify'
```

Without this entry, the SourceType will be handled by the default provider (defined by `COLLECT_DEFAULT_PROVIDER`).

---

## Step 7: Unit Tests for the Normalizer

Create unit tests for the normalizer before testing the complete flow.

File: `tests/Units/Infrastructure/Collect/Apify/Normalizer/MyNewSourceNormalizerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Infrastructure\Collect\Apify\Normalizer\MyNewSourceNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MyNewSourceNormalizer::class)]
final class MyNewSourceNormalizerTest extends TestCase
{
    private MyNewSourceNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MyNewSourceNormalizer();
    }

    public function testSupportsCorrectActor(): void
    {
        self::assertTrue($this->normalizer->supports('owner/actor-name'));
        self::assertFalse($this->normalizer->supports('other/actor'));
    }

    public function testNormalizesValidItem(): void
    {
        $item = [
            'url'            => 'https://example.com/article',
            'title'          => 'My article',
            'text'           => 'Article content',
            'published_date' => '2026-04-27T10:00:00Z',
        ];

        $document = $this->normalizer->normalize($item, new NormalizerContext('owner/actor-name', 'dataset-id', 0));

        self::assertNotNull($document);
        self::assertSame('https://example.com/article', $document->url);
        self::assertSame('My article', $document->title);
        self::assertStringContainsString('apify', $document->getProviderId());
    }

    public function testReturnsNullWhenUrlMissing(): void
    {
        $item = ['title' => 'Article without URL'];

        $document = $this->normalizer->normalize($item, new NormalizerContext('owner/actor-name', 'dataset-id', 0));

        self::assertNull($document);
    }

    public function testReturnsNullWhenTitleMissing(): void
    {
        $item = ['url' => 'https://example.com'];

        $document = $this->normalizer->normalize($item, new NormalizerContext('owner/actor-name', 'dataset-id', 0));

        self::assertNull($document);
    }
}
```

Run the tests:

```bash
docker exec chapsmind-target-1 php vendor/bin/phpunit \
  tests/Units/Infrastructure/Collect/Apify/Normalizer/MyNewSourceNormalizerTest.php \
  --no-coverage
```

---

## Step 8: Test the Complete Flow

Verify that existing integration tests still pass:

```bash
docker exec chapsmind-target-1 php vendor/bin/phpunit \
  tests/Integration/Collect/ \
  --no-coverage
```

For end-to-end manual testing:

1. Create a source of the new SourceType in the application.
2. Trigger a collection via the UI or CLI.
3. Verify in logs that `ApifyProviderGateway` submitted the run:
   ```
   grep "apify" var/log/dev.log | grep "run"
   ```
4. Simulate the incoming webhook (or wait for the real Apify run) and verify documents appear.
5. Verify in `source_activities` that `source_collect_cost` was created with cost metrics.

### Verify Apify Configuration

```bash
docker exec chapsmind-target-1 php bin/console debug:container --parameter=app.apify
```

---

## Summary of Files to Create/Modify

| File                                                                    | Action | Description                        |
| ----------------------------------------------------------------------- | ------ | ---------------------------------- |
| `src/Domain/Source/SourceType.php`                                      | Modify | Add case (if new SourceType)       |
| `config/services/collect_provider.yaml`                                 | Modify | Actor mapping + template + routing |
| `src/Infrastructure/Collect/Apify/Normalizer/MyNewSourceNormalizer.php` | Create | Normalizer for the actor           |
| `tests/Units/Infrastructure/Collect/Apify/Normalizer/...Test.php`       | Create | Unit tests for the normalizer      |
