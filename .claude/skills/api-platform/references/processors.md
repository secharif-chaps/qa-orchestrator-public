# API Platform Processor Patterns

Processors handle write operations (POST, PUT, PATCH, DELETE) for API resources.

## Directory Structure

```
api/src/Infrastructure/{BoundedContext}/
├── {Entity}Processor.php              # Create (POST)
├── Update{Entity}Processor.php        # Update (PATCH/PUT)
├── Delete{Entity}Processor.php        # Delete (DELETE)
└── {Custom}Processor.php              # Custom operations
```

## Basic Processor Pattern

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\CreateWatchFileAction;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\UserInterface\Dto\CreateWatchFileDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<CreateWatchFileDto, WatchFile>
 */
class WatchFileProcessor implements ProcessorInterface
{
    use HandleTrait;

    // ⚠️ MessageBus CANNOT use constructor promotion when using HandleTrait
    // HandleTrait declares private $messageBus itself — assign manually
    public function __construct(
        MessageBusInterface $messageBus,
        private readonly Security $security,
    ) {
        $this->messageBus = $messageBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        $user = $this->security->getUser();
        assert($user instanceof User);

        return $this->handle(new CreateWatchFileAction(
            name: $data->name,
            ownerId: $user->getId(),
            type: $data->type,
        ));
    }
}
```

## Update Processor

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\UpdateWatchFileAction;
use App\Domain\WatchFile\WatchFile;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<WatchFile, WatchFile>
 */
class UpdateWatchFileProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        return $this->handle(new UpdateWatchFileAction(
            id: $uriVariables['id'],
            name: $data->getName(),
            description: $data->getDescription(),
        ));
    }
}
```

## Delete Processor

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\DeleteWatchFileAction;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<mixed, null>
 */
class DeleteWatchFileProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->handle(new DeleteWatchFileAction(
            id: $uriVariables['id'],
        ));

        return null;
    }
}
```

## Custom Operation Processor

For sub-resource operations like `/watch_files/{id}/status/{status}`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<mixed, WatchFile>
 */
class ChangeWatchFileStatusProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        return $this->handle(new ChangeWatchFileStatusAction(
            id: $uriVariables['id'],
            status: WatchFileStatus::from($uriVariables['status']),
        ));
    }
}
```

## Processor with Event Dispatching

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<WatchFile, WatchFile>
 */
readonly class WatchFileProcessor implements ProcessorInterface
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
        private ?LoggerInterface $logger = null,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        $user = $this->security->getUser();

        $watchFile = WatchFile::create($data->name, $user);
        $this->watchFileGateway->save($watchFile);

        // Dispatch domain event
        $this->eventDispatcher->dispatch(new WatchFileCreatedEvent($watchFile, $user));

        $this->logger?->info('WatchFile created', [
            'watch_file_id' => $watchFile->getId(),
        ]);

        return $watchFile;
    }
}
```

## Processor with Async Message

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\AnalyzeWatchFileAction;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<WatchFile, WatchFile>
 */
readonly class WatchFileProcessor implements ProcessorInterface
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private MessageBusInterface $messageBus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        $watchFile = WatchFile::create($data->name, $data->owner);
        $this->watchFileGateway->save($watchFile);

        // Queue async analysis
        $this->messageBus->dispatch(
            new AnalyzeWatchFileAction($watchFile->getId())
        );

        return $watchFile;
    }
}
```

## Registration in ApiResource

```php
#[ApiResource(
    operations: [
        new Post(
            input: CreateWatchFileDto::class,
            processor: WatchFileProcessor::class,
        ),
        new Patch(
            processor: UpdateWatchFileProcessor::class,
        ),
        new Delete(
            processor: DeleteWatchFileProcessor::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{id}/status/{status}',
            read: false,
            processor: ChangeWatchFileStatusProcessor::class,
        ),
    ],
)]
```

## Key Rules

1. **Use HandleTrait**: Dispatch actions, never call handlers directly; use `Symfony\Component\Messenger\HandleTrait`
2. **Type Annotations**: Use `@implements ProcessorInterface<Input, Output>`
3. **Readonly Rules**: Processors using `HandleTrait` MUST NOT be `readonly class` — HandleTrait declares `private $messageBus` which conflicts. Processors without HandleTrait CAN be `readonly`.
4. **MessageBus with HandleTrait**: Do NOT use constructor promotion for `$messageBus`; assign manually: `public function __construct(MessageBusInterface $messageBus) { $this->messageBus = $messageBus; }`
5. **Return Entity**: Return the created/updated entity for serialization
6. **Return Null for Delete**: DELETE operations return `null`
7. **Extract User from Security**: Use `$this->security->getUser()`
8. **URI Variables**: Access route parameters via `$uriVariables`
9. **Log Important Actions**: Use optional logger for observability
