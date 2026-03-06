---
name: php-symfony
description: PHP 8.4 with Symfony 7.3 development standards for the Basil backend. Use when writing any PHP code including services, handlers, controllers, commands, or entities. Activates when working on .php files in api/src/, using dependency injection, creating Symfony services, implementing message handlers with #[AsMessageHandler], or configuring services in config/services.yaml. CRITICAL - Always use declare(strict_types=1), readonly classes where appropriate, constructor property promotion, and dependency injection via constructor.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When writing or editing any `.php` file in `api/src/`
- When creating Symfony services with dependency injection
- When implementing message handlers with `#[AsMessageHandler]`
- When creating console commands with `#[AsCommand]`
- When using PHP 8.4 features (constructor promotion, readonly, match, named arguments)
- When configuring services in `config/services.yaml`
- When implementing Symfony event subscribers or listeners
- When working with Symfony security (voters, authenticators)
- When adding `declare(strict_types=1)` to PHP files
- When using PHP attributes for configuration
- When dispatching async messages via `MessageBusInterface`

# PHP 8.4 + Symfony 7.3 Standards

**CRITICAL**: Always use `declare(strict_types=1);` at the top of every PHP file.

## Basil Directory Structure

```
api/
├── src/
│   ├── Domain/              # Business entities and interfaces
│   │   ├── WatchFile/
│   │   ├── Actor/
│   │   ├── Document/
│   │   ├── Source/
│   │   ├── Chat/
│   │   └── Shared/
│   ├── Application/         # Use cases (Action/Handler)
│   ├── Infrastructure/      # Doctrine, API Platform, external services
│   └── UserInterface/       # DTOs, CLI commands
├── config/
│   ├── packages/           # Symfony bundle configuration
│   └── services.yaml       # Service definitions
├── migrations/             # Doctrine migrations
├── templates/prompts/      # AI prompt templates (Twig)
└── tests/
    ├── Units/              # Unit tests with NullGateway
    └── Integration/        # API tests with database
```

## Handler Class Structure (Real Basil Example)

```php
// api/src/Application/WatchFile/Workflow/ProcessWatchFileClassificationResultHandler.php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\DomainException;
use App\Domain\WatchFile\AnalysisResultGatewayInterface;
use App\Domain\WatchFile\WatchFileTypeClassificationResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessWatchFileClassificationResultHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly AnalysisResultGatewayInterface $analysisResultGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ProcessWatchFileClassificationResultAction $action): void
    {
        try {
            $classificationResult = WatchFileTypeClassificationResult::fromArray($action->classification);
            $watchFile = $this->getWatchFile($action->watchFileId);

            // Store classification
            $analysisResult = $this->analysisResultGateway->getLastOrCreate($watchFile);
            $analysisResult->setClassificationData($classificationResult);
            $this->analysisResultGateway->save($analysisResult);

            // Domain method with business rule
            if ($classificationResult->primaryType->confidenceScore >= 60) {
                $watchFile->detectMonitoringType($classificationResult->primaryType->type);
            }

            $this->watchFileGateway->save($watchFile);

            $this->logger?->info('Classification processed', [
                'watch_file_id' => $watchFile->getId(),
            ]);
        } catch (DomainException $e) {
            $this->logger?->error('Failed to process classification', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

## Key Conventions

### Readonly by Default

```php
// ✅ Good: readonly class
readonly class CreateWatchFileAction
{
    public function __construct(
        public string $name,
        public UserId $ownerId,
    ) {}
}

// ❌ Bad: mutable class without reason
class CreateWatchFileAction
{
    public string $name;
    public UserId $ownerId;
}
```

### Constructor Property Promotion

```php
// ✅ Good: promoted properties
public function __construct(
    private readonly WatchFileGatewayInterface $gateway,
    private readonly LoggerInterface $logger,
) {}

// ❌ Bad: old-style assignment
public function __construct(WatchFileGatewayInterface $gateway)
{
    $this->gateway = $gateway;
}
```

### No Final Classes

Avoid `final` keyword (project policy for testing flexibility):

```php
// ✅ Good
readonly class WatchFileHandler {}

// ❌ Bad (except Doctrine migrations)
final class WatchFileHandler {}
```

### DateTimeImmutable Always

```php
// ✅ Good
private \DateTimeImmutable $createdAt;

// ❌ Bad
private \DateTime $createdAt;
```

## Dependency Injection

Always inject via constructor, never use service locator:

```php
// ✅ Good: constructor injection
readonly class WatchFileHandler
{
    public function __construct(
        private WatchFileGatewayInterface $gateway,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}
}

// ❌ Bad: service locator
$gateway = $container->get(WatchFileGatewayInterface::class);
```

## Null Handling

```php
// ✅ Good: nullsafe operator
$ownerName = $watchFile->getOwner()?->getUser()?->getDisplayName();

// ✅ Good: null coalescing
$name = $dto->name ?? 'Default';

// ✅ Good: throw on missing required data
$watchFile = $this->gateway->find($id)
    ?? throw new WatchFileNotFoundException($id);
```

## Array Handling

```php
// ✅ Good: array functions
$ids = array_map(fn(WatchFile $wf) => $wf->getId(), $watchFiles);
$active = array_filter($watchFiles, fn(WatchFile $wf) => $wf->isActive());

// ✅ Good: spread operator
$merged = [...$existingItems, ...$newItems];
```

## Symfony Messenger (Async Actions)

```php
// Dispatch async action
$this->messageBus->dispatch(
    new ShareWatchFileBatchAction(
        [new ShareWatchFileAction($watchFile->getId(), $userId, WatchFileUserRole::OWNER)],
        $userId,
        new \DateTimeImmutable(),
    ),
);

// Dispatch sync action and get result (via HandleTrait)
use Symfony\Component\Messenger\HandleTrait;

class MyProcessor
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function doSomething(): WatchFile
    {
        return $this->handle(new CreateWatchFileAction(...));
    }
}
```

## Doctrine Entity (Real Basil Example)

```php
// api/src/Domain/WatchFile/WatchFile.php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Index(columns: ['name'])]
#[ORM\Index(columns: ['status'])]
class WatchFile
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watchfile:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['watchfile:read', 'watchfile:write'])]
    private string $name;

    #[ORM\Column(type: 'string', enumType: WatchFileStatus::class)]
    #[Groups(['watchfile:read'])]
    private WatchFileStatus $status = WatchFileStatus::NEW;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $description, ?User $createdBy)
    {
        $this->name = $name;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Domain method with business rule
    public function detectMonitoringType(MonitoringType $type): void
    {
        $this->monitoringType = $type;
        $this->status = WatchFileStatus::MONITORING_TYPE_DETECTED;
    }
}
```

## Enums (PHP 8.1+)

```php
// api/src/Domain/WatchFile/WatchFileStatus.php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum WatchFileStatus: string
{
    case NEW = 'NEW';
    case NEEDS_ANALYZED = 'NEEDS_ANALYZED';
    case QUESTIONS_GENERATED = 'QUESTIONS_GENERATED';
    case ACTORS_DETECTED = 'ACTORS_DETECTED';
    case SOURCES_DETECTED = 'SOURCES_DETECTED';
    case MONITORING_TYPE_DETECTED = 'MONITORING_TYPE_DETECTED';
    case ENABLED = 'ENABLED';
    case ARCHIVED = 'ARCHIVED';
}
```

## Event Dispatcher

```php
// Dispatch domain event
use Psr\EventDispatcher\EventDispatcherInterface;

$this->eventDispatcher->dispatch(new WatchFileCreatedEvent($watchFile, $user));

// Event class
readonly class WatchFileCreatedEvent
{
    public function __construct(
        public WatchFile $watchFile,
        public User $user,
    ) {}
}
```

## Documentation

- [symfony-services.md](references/symfony-services.md) - Service configuration
- [doctrine-entities.md](references/doctrine-entities.md) - Entity mapping
