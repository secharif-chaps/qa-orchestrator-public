---
name: clean-architecture
description: Clean Architecture with Domain-Driven Design (DDD) for the Basil PHP/Symfony backend. Use when creating new domain entities in api/src/Domain/, implementing use cases with the Action/Handler pattern in api/src/Application/, creating Gateway interfaces for data access, implementing Doctrine repositories in api/src/Infrastructure/, or structuring new features across architecture layers. CRITICAL - Domain layer has zero dependencies on Infrastructure; Application layer depends only on Domain; Infrastructure implements interfaces defined in Domain.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating new domain entities in `api/src/Domain/{Entity}/`
- When implementing use cases with Action/Handler pattern in `api/src/Application/`
- When defining Gateway interfaces in `api/src/Domain/{Entity}/` (e.g., `WatchFileGatewayInterface`)
- When implementing Doctrine repositories in `api/src/Infrastructure/{Entity}/`
- When structuring a new feature across all architecture layers
- When ensuring proper layer dependencies (Domain → Application → Infrastructure)
- When creating value objects (e.g., `TranslatedText`) or domain services
- When working with entity state machines (e.g., `WatchFileStatus` enum)
- When deciding where to place new code (which layer)
- When creating domain events or exceptions

# Clean Architecture + DDD Standards

**CRITICAL**: Respect layer dependencies. Domain MUST NOT import from Application or Infrastructure.

## Basil Layer Structure

```
api/src/
├── Domain/              # Core business logic (framework-agnostic)
│   ├── WatchFile/
│   │   ├── WatchFile.php                    # Entity with business logic
│   │   ├── WatchFileStatus.php              # Enum (state machine)
│   │   ├── WatchFileUserRole.php            # Enum (owner, editor, viewer)
│   │   ├── WatchFileGatewayInterface.php    # Gateway contract
│   │   ├── AnalysisResult.php               # Value object
│   │   ├── MonitoringType.php               # Enum
│   │   ├── Event/WatchFileCreatedEvent.php  # Domain event
│   │   └── Exception/WatchFileNotFoundException.php
│   ├── Actor/
│   │   ├── Actor.php
│   │   ├── ActorType.php
│   │   └── ActorGatewayInterface.php
│   ├── Document/
│   ├── Source/
│   ├── Chat/
│   └── Shared/
│       ├── TranslatedText.php               # Value object (en/fr)
│       ├── CreatedByInterface.php
│       └── EntityEnrichmentOrchestratorInterface.php
│
├── Application/         # Use cases (Action/Handler pairs)
│   └── WatchFile/
│       ├── Chat/
│       │   ├── CreateConversationAction.php
│       │   └── CreateConversationHandler.php
│       ├── Share/
│       │   ├── ShareWatchFileAction.php
│       │   └── ShareWatchFileHandler.php
│       ├── Workflow/
│       │   ├── ProcessWatchFileClassificationResultAction.php
│       │   └── ProcessWatchFileClassificationResultHandler.php
│       ├── Actor/
│       │   ├── AddActorAction.php
│       │   └── AddActorHandler.php
│       └── Source/
│           ├── AddSourceAction.php
│           └── AddSourceHandler.php
│
├── Infrastructure/      # External concerns
│   └── WatchFile/
│       ├── WatchFileDoctrineGateway.php     # Doctrine implementation
│       ├── WatchFileProcessor.php           # API Platform POST
│       ├── UpdateWatchFileProcessor.php     # API Platform PATCH
│       ├── WatchFileProvider.php            # API Platform GET
│       ├── WatchFileCollectionProvider.php  # API Platform GET collection
│       └── ApiFilter/
│           ├── UserAccessibleWatchFileFilter.php
│           └── OnlyFavoritesFilter.php
│
└── UserInterface/       # HTTP/CLI delivery
    ├── Dto/
    │   ├── Chat/UserMessageDto.php
    │   └── WatchFile/ShareWatchFileInputDto.php
    └── Command/                              # Symfony CLI commands
```

## Domain Layer

### Entity (Real Basil Example)

Basil entities are Doctrine ORM entities — they use `class` (NOT `readonly class`) and plain `string` IDs with UUID generation. See `php-symfony/SKILL.md` for the full entity pattern with ORM attributes.

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;

// ⚠️ NOT readonly — Doctrine needs to modify entity state
#[ORM\Entity]
#[ORM\Index(columns: ['status'])]
class WatchFile
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', enumType: WatchFileStatus::class)]
    private WatchFileStatus $status = WatchFileStatus::NEW;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, ?User $createdBy = null)
    {
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Domain method with business rule
    public function detectMonitoringType(MonitoringType $type): void
    {
        $this->monitoringType = $type;
        $this->status = WatchFileStatus::MONITORING_TYPE_DETECTED;
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
}
```

### Gateway Interface (Real Basil Example)

```php
// api/src/Domain/WatchFile/WatchFileGatewayInterface.php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\UsageLimit\ResourceCount;
use App\Domain\User\User;

interface WatchFileGatewayInterface
{
    /**
     * @param string    $id   The ID of the WatchFile to retrieve
     * @param User|null $user optional user to enrich with virtual properties
     */
    public function get(string $id, ?User $user = null): WatchFile;

    public function save(WatchFile $watchFile): void;

    public function getForUser(string $id, User $user): WatchFile;

    /**
     * @return list<string> An array of WatchFile IDs accessible by user
     */
    public function findAllIdsByUser(User $user): array;

    /**
     * Count active WatchFiles for a given user.
     */
    public function countActiveByUserId(string $userId): ResourceCount;

    /**
     * Check if an actor relation already exists for this watchfile.
     * Business rule: an actor can only be linked once.
     */
    public function hasActorRelation(string $watchFileId, string $actorId): bool;
}
```

### Value Object (TranslatedText)

```php
// api/src/Domain/Shared/TranslatedText.php
<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Represents a multilingual text value (en/fr).
 */
readonly class TranslatedText
{
    public function __construct(
        public string $en = '',
        public string $fr = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            en: $data['en'] ?? '',
            fr: $data['fr'] ?? '',
        );
    }

    public function toArray(): array
    {
        return ['en' => $this->en, 'fr' => $this->fr];
    }
}
```

## Application Layer

### Action (Command DTO)

```php
// api/src/Application/WatchFile/Workflow/ProcessWatchFileClassificationResultAction.php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow;

readonly class ProcessWatchFileClassificationResultAction
{
    /**
     * @param array{
     *     primaryType: string,
     *     confidenceScore: int,
     *     justification: array{en: string, fr: string},
     *     secondaryTypes: list<array{type: string, score: int}>,
     *     explanation: array{keywords: list<string>}
     * } $classification
     */
    public function __construct(
        public string $watchFileId,
        public array $classification,
    ) {}
}
```

### Handler (Real Basil Example)

```php
// api/src/Application/WatchFile/Workflow/ProcessWatchFileClassificationResultHandler.php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\DomainException;
use App\Domain\WatchFile\AnalysisResultGatewayInterface;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileTypeClassificationResult;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessWatchFileClassificationResultHandler
{
    use GetWatchFileTrait;  // Provides $this->watchFileGateway

    public function __construct(
        private readonly WatchFileActivityGatewayInterface $activityGateway,
        private readonly WatchFileActivityLogger $activityLogger,
        private readonly AnalysisResultGatewayInterface $analysisResultGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ProcessWatchFileClassificationResultAction $action): void
    {
        try {
            $classificationResult = WatchFileTypeClassificationResult::fromArray($action->classification);
            $watchFile = $this->getWatchFile($action->watchFileId);

            // Store classification in analysis result
            $analysisResult = $this->analysisResultGateway->getLastOrCreate($watchFile);
            $analysisResult->setClassificationData($classificationResult);
            $this->analysisResultGateway->save($analysisResult);

            // Set monitoring type if confident enough
            if ($classificationResult->primaryType->confidenceScore >= 60) {
                $watchFile->detectMonitoringType($classificationResult->primaryType->type);
            }

            $this->watchFileGateway->save($watchFile);

            $this->logger?->info('Classification processed', [
                'watch_file_id' => $watchFile->getId(),
                'confidence' => $classificationResult->primaryType->confidenceScore,
            ]);
        } catch (DomainException $e) {
            $this->logger?->error('Failed to process classification', [
                'watch_file_id' => $action->watchFileId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

## Infrastructure Layer

### Gateway Implementation (Real Basil Example)

```php
// api/src/Infrastructure/WatchFile/WatchFileDoctrineGateway.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use Doctrine\ORM\EntityManagerInterface;

class WatchFileDoctrineGateway implements WatchFileGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {
    }

    public function get(string $id, ?User $user = null): WatchFile
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('f')
            ->addSelect('fu')  // Eager load relations
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'fu')
            ->where('f.id = :id')
            ->setParameter('id', $id);

        $watchFile = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$watchFile instanceof WatchFile) {
            throw new WatchFileNotFoundException(\sprintf('WatchFile %s not found', $id));
        }

        // Enrich with computed properties (isFavorite, userRole)
        return $this->watchFileEnricher->enrich($watchFile, ['user' => $user]);
    }

    public function save(WatchFile $watchFile): void
    {
        $watchFile->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($watchFile);
        $this->entityManager->flush();
    }

    public function countActiveByUserId(string $userId): ResourceCount
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT f.id)')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'wfu')
            ->where('wfu.user = :userId')
            ->andWhere('wfu.role = :role')
            ->andWhere('f.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('role', WatchFileUserRole::OWNER)
            ->setParameter('status', WatchFileStatus::ENABLED)
            ->getQuery()
            ->getSingleScalarResult();

        return ResourceCount::fromInt((int) $count);
    }

    public function hasActorRelation(string $watchFileId, string $actorId): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(wfa.id)')
            ->from(WatchFileActor::class, 'wfa')
            ->where('wfa.watchFile = :watchFileId')
            ->andWhere('wfa.actor = :actorId')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('actorId', $actorId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
```

## Key Rules

### Layer Dependencies

```php
// ✅ Domain can only use Domain
namespace App\Domain\WatchFile;
use App\Domain\User\User;

// ✅ Application can use Domain
namespace App\Application\WatchFile;
use App\Domain\WatchFile\WatchFile;

// ✅ Infrastructure can use Domain and Application
namespace App\Infrastructure\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Application\WatchFile\CreateWatchFileHandler;

// ❌ Domain CANNOT use Infrastructure
namespace App\Domain\WatchFile;
use App\Infrastructure\WatchFile\WatchFileDoctrineGateway; // FORBIDDEN
```

### Use HandleTrait for Dispatching

```php
// ✅ Good: via HandleTrait
use HandleTrait;
$result = $this->handle(new CreateWatchFileAction(...));

// ❌ Bad: direct handler invocation
$this->handler->__invoke($action);
```

## Documentation

- [domain.md](references/domain.md) - Domain patterns
- [application.md](references/application.md) - Action/Handler patterns
- [infrastructure.md](references/infrastructure.md) - Gateway implementations
