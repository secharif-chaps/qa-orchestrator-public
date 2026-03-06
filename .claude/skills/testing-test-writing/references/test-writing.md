# Test Writing Standards

## Philosophy

- **Write Minimal Tests During Development**: Do NOT write tests for every change. Complete the feature first, then add strategic tests at logical completion points.
- **Test Only Core User Flows**: Write tests for critical paths and primary workflows. Skip non-critical utilities until explicitly requested.
- **Defer Edge Case Testing**: Do NOT test edge cases or validation logic unless business-critical. Address these in dedicated testing phases.
- **Test Behavior, Not Implementation**: Focus on what code does, not how it does it, to reduce brittleness.

## Backend Testing (PHPUnit)

### Test Types

| Type            | Location             | Purpose                       | Speed           |
| --------------- | -------------------- | ----------------------------- | --------------- |
| **Unit**        | `tests/Units/`       | Domain logic with NullGateway | Fast (ms)       |
| **Integration** | `tests/Integration/` | Full HTTP-to-database flow    | Slower (100ms+) |

### Unit Test Pattern

Use **NullGateway** pattern (in-memory implementations) for isolation:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\ManualValidateDocumentAction;
use App\Application\Document\ManualValidateDocumentHandler;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ManualValidateDocumentHandler::class)]
class ManualValidateDocumentHandlerTest extends TestCase
{
    private ManualValidateDocumentHandler $handler;
    private NullDocumentGateway $documentGateway;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();

        $this->handler = new ManualValidateDocumentHandler(
            $this->documentGateway,
            new NullLogger(),
        );
    }

    public function testHandleAcceptDocument(): void
    {
        // Arrange
        $document = $this->createDocument();
        $this->documentGateway->save($document);

        // Act
        ($this->handler)(new ManualValidateDocumentAction(
            documentId: $document->getId(),
            action: ManualValidationStatus::ACCEPTED,
        ));

        // Assert
        $this->assertEquals(1, $this->documentGateway->count());
    }
}
```

### Integration Test Pattern

Use **Foundry factories** and **AbstractApiTestCase**:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;

class WatchFileApiTest extends AbstractApiTestCase
{
    public function testPostWatchFileFavorite(): void
    {
        // Arrange - Use Foundry factories
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Act
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/favorite", [
            'json' => [],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(204);
    }

    public function testGetWatchFileUnauthorized(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $otherUser = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($otherUser);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }
}
```

### Test Naming Convention

```php
// Pattern: test{Action}{Scenario}
public function testCreateWatchFileSuccess(): void {}
public function testCreateWatchFileValidationError(): void {}
public function testGetWatchFileNotFound(): void {}
public function testDeleteWatchFileUnauthorized(): void {}
```

### Key Rules

1. **Use Foundry Factories**: Never create entities manually

    ```php
    // ✅ Good
    $user = UserFactory::createOne();

    // ❌ Bad
    $user = new User('id', 'email@test.com', ['ROLE_USER']);
    ```

2. **Store data before API calls**: Doctrine proxies require data retrieval before first request

    ```php
    $watchFileId = $watchFile->getId(); // Store before request
    $client->request('GET', "/api/watch_files/{$watchFileId}");
    ```

3. **Use NullLogger**: Never test `LoggerInterface` unless it adds value

    ```php
    new MyHandler($gateway, new NullLogger());
    ```

4. **Use #[CoversClass]**: Always specify coverage attribute on unit tests
    ```php
    #[CoversClass(MyHandler::class)]
    class MyHandlerTest extends TestCase {}
    ```

## Frontend Testing (Vitest)

### Component Test Pattern

```typescript
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/vue'
import userEvent from '@testing-library/user-event'
import WatchFileCard from '@/components/watchFiles/WatchFileCard.vue'

describe('WatchFileCard', () => {
    it('displays watchFile name', () => {
        render(WatchFileCard, {
            props: {
                watchFile: {
                    id: '123',
                    name: 'Test WatchFile',
                    status: 'active',
                },
            },
        })

        expect(screen.getByText('Test WatchFile')).toBeInTheDocument()
    })

    it('emits delete event on button click', async () => {
        const user = userEvent.setup()
        const { emitted } = render(WatchFileCard, {
            props: { watchFile: mockWatchFile },
        })

        await user.click(screen.getByRole('button', { name: /delete/i }))

        expect(emitted('delete')).toHaveLength(1)
    })
})
```

### Store Test Pattern

```typescript
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useWatchFileStore } from '@/stores/watchFile'

describe('WatchFileStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('updates filters correctly', () => {
        const store = useWatchFileStore()

        store.setFilters({ status: 'active', search: 'test' })

        expect(store.filters.status).toBe('active')
        expect(store.filters.search).toBe('test')
    })
})
```

## N8N Workflow Testing

### Test Dataset Structure

```json
{
    "version": "1.0",
    "metadata": {
        "workflowId": "abc123",
        "workflowName": "Validate Document",
        "description": "Test document validation workflow"
    },
    "testCases": [
        {
            "testCaseId": "TC-001",
            "name": "Valid document - High relevance",
            "description": "Document matching all reference subject criteria",
            "expectedOutput": "success",
            "successCheck": {
                "enabled": true,
                "expectedPathValue": [
                    ["aiValidation.status", "validated"],
                    ["aiValidation.confidenceScore", 85]
                ]
            },
            "input": {
                "document": {
                    "id": "doc-001",
                    "content": "Market analysis...",
                    "referenceSubject": "Competitive intelligence"
                }
            }
        }
    ]
}
```

### Running N8N Tests

```bash
# Setup (first time)
task n8n:test:setup

# Run tests
task n8n:test -- reference-subject-workflow-evaluation.json

# Update snapshots
task n8n:test -- reference-subject-workflow-evaluation.json --update-snapshots
```

## Commands Reference

```bash
# Backend
task api:test                           # Run all PHPUnit tests
task api:test:coverage                  # Run with coverage report
docker compose exec api php vendor/bin/phpunit --filter=testMethodName

# Frontend
docker compose exec pwa npm run test -- --run

# N8N
task n8n:test -- <dataset-filename>
task n8n:validate:rabbitmq
```

## Best Practices Summary

| Practice                       | Description                                                    |
| ------------------------------ | -------------------------------------------------------------- |
| **Clear Names**                | `test{Action}{Scenario}` - describes what and expected outcome |
| **Mock External Dependencies** | Database, APIs, file systems, AI services                      |
| **Fast Execution**             | Unit tests should run in milliseconds                          |
| **Arrange-Act-Assert**         | Structure tests in clear phases                                |
| **One Assertion Concept**      | Test one behavior per test method                              |
| **No Logic in Tests**          | Avoid conditionals and loops in test code                      |

## Anti-Patterns to Avoid

- ❌ Testing implementation details instead of behavior
- ❌ Creating entities manually instead of using Foundry factories
- ❌ Testing logger calls unless business-critical
- ❌ Writing tests for every edge case during feature development
- ❌ Sharing state between tests
- ❌ Testing private methods directly
