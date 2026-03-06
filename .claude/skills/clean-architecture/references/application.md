# Application Layer Patterns

The Application layer orchestrates use cases using the Action/Handler pattern (CQRS-light).

## Directory Structure

```
api/src/Application/
├── {BoundedContext}/
│   ├── Create{Entity}Action.php       # Command DTO
│   ├── Create{Entity}Handler.php      # Command handler
│   ├── Update{Entity}Action.php
│   ├── Update{Entity}Handler.php
│   ├── Delete{Entity}Action.php
│   ├── Delete{Entity}Handler.php
│   └── Get{Entity}Query.php           # Query (optional)
└── Shared/
    ├── SyncActionInterface.php        # Marker for sync actions
    ├── AsyncActionInterface.php       # Marker for async actions
    └── HandleTrait.php                # Dispatcher helper
```

## Action Pattern (Command DTO)

Actions are immutable DTOs that carry the intent and data for a use case.

### Sync Action (returns result)

```php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\Shared\SyncActionInterface;
use App\Domain\User\UserId;
use App\Domain\WatchFile\WatchFileType;

readonly class CreateWatchFileAction implements SyncActionInterface
{
    public function __construct(
        public string $name,
        public UserId $ownerId,
        public ?WatchFileType $type = null,
        public ?string $description = null,
    ) {}
}
```

### Async Action (queued processing)

```php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\Shared\AsyncActionInterface;
use App\Domain\WatchFile\WatchFileId;

readonly class AnalyzeWatchFileAction implements AsyncActionInterface
{
    public function __construct(
        public WatchFileId $watchFileId,
    ) {}

    public static function getTransport(): string
    {
        return 'async_priority_high';
    }
}
```

## Handler Pattern

Handlers execute the business logic for an action. One handler per action.

### Basic Handler

```php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\User\UserGatewayInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CreateWatchFileHandler
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private UserGatewayInterface $userGateway,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(CreateWatchFileAction $action): WatchFile
    {
        // 1. Load dependencies from gateways
        $owner = $this->userGateway->get($action->ownerId);

        // 2. Execute domain logic
        $watchFile = WatchFile::create(
            name: $action->name,
            owner: $owner,
        );

        if ($action->type !== null) {
            $watchFile->setType($action->type);
        }

        // 3. Persist changes
        $this->watchFileGateway->save($watchFile);

        // 4. Log for observability
        $this->logger->info('WatchFile created', [
            'watchfile_id' => (string) $watchFile->getId(),
            'owner_id' => (string) $action->ownerId,
        ]);

        return $watchFile;
    }
}
```

### Handler with Multiple Gateways

```php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileNotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ArchiveWatchFileHandler
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private DocumentGatewayInterface $documentGateway,
    ) {}

    public function __invoke(ArchiveWatchFileAction $action): void
    {
        $watchFile = $this->watchFileGateway->get($action->watchFileId);

        // Domain logic in entity
        $watchFile->archive($action->archivedBy);

        // Archive related documents
        $documents = $this->documentGateway->findByWatchFile($action->watchFileId);
        foreach ($documents as $document) {
            $document->archive();
            $this->documentGateway->save($document);
        }

        $this->watchFileGateway->save($watchFile);
    }
}
```

### Handler Dispatching Another Action

```php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\Shared\HandleTrait;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class ProcessWatchFileHandler
{
    use HandleTrait;

    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(ProcessWatchFileAction $action): void
    {
        $watchFile = $this->watchFileGateway->get($action->watchFileId);

        // Update status
        $watchFile->markAsProcessing();
        $this->watchFileGateway->save($watchFile);

        // Dispatch async action for heavy processing
        $this->handle(new AnalyzeWatchFileAction($action->watchFileId));
    }
}
```

## HandleTrait Usage

Use `HandleTrait` to dispatch actions from processors, providers, or other handlers.

```php
<?php

declare(strict_types=1);

namespace App\Application\Shared;

use Symfony\Component\Messenger\HandleTrait as SymfonyHandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

trait HandleTrait
{
    use SymfonyHandleTrait;

    private MessageBusInterface $messageBus;

    // Usage in any class with HandleTrait:
    // $result = $this->handle(new SomeAction(...));
}
```

### In API Platform Processor

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Application\Shared\HandleTrait;
use App\Application\WatchFile\CreateWatchFileAction;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class WatchFileProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Dispatch action via HandleTrait
        return $this->handle(new CreateWatchFileAction(
            name: $data->name,
            ownerId: $data->ownerId,
        ));
    }
}
```

## Action Interfaces

```php
<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Marker interface for synchronous actions that return a result.
 * Processed immediately via MessageBus::handle().
 */
interface SyncActionInterface {}

/**
 * Marker interface for asynchronous actions.
 * Queued to RabbitMQ for background processing.
 */
interface AsyncActionInterface
{
    /**
     * Returns the transport/queue name.
     */
    public static function getTransport(): string;
}
```

## Key Rules

1. **One Handler per Action**: Each action has exactly one handler
2. **Handler Naming**: `{ActionName}` → `{ActionName}Handler` (drop "Action" suffix)
3. **Readonly Classes**: Both actions and handlers should be `readonly`
4. **Use HandleTrait**: Never call handlers directly; always dispatch via message bus
5. **Domain Logic in Entities**: Handlers orchestrate, entities enforce rules
6. **No HTTP Concerns**: No Request/Response objects in Application layer
7. **Inject Gateways**: Use gateway interfaces, not concrete implementations
8. **Log Important Events**: Add structured logging for observability
