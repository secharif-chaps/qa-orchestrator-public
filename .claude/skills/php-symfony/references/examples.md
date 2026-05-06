# PHP/Symfony Examples

## Handler with Message Handler Attribute

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

---

## Symfony Messenger Dispatch Patterns

### Async Dispatch

```php
// Dispatch to message queue (async)
$this->messageBus->dispatch(
    new ShareWatchFileBatchAction(
        [new ShareWatchFileAction($watchFile->getId(), $userId, WatchFileUserRole::OWNER)],
        $userId,
        new \DateTimeImmutable(),
    ),
);

// Dispatch with stamps
use Symfony\Component\Messenger\Stamp\DelayStamp;

$this->messageBus->dispatch(
    new ProcessDocumentAction($documentId),
    [new DelayStamp(5000)] // 5 second delay
);
```

### Sync Dispatch with HandleTrait

```php
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class WatchFileProcessor
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function process(): WatchFile
    {
        // Synchronous dispatch - returns result from handler
        return $this->handle(new CreateWatchFileAction(
            name: 'New WatchFile',
            ownerId: $userId,
        ));
    }
}
```

---

## Doctrine Entity with Full Mapping

```php
// api/src/Domain/WatchFile/WatchFile.php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\CreatedByInterface;
use App\Domain\Shared\CreatedByTrait;
use App\Domain\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'watch_file')]
#[ORM\Index(columns: ['name'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['updated_at'])]
class WatchFile implements CreatedByInterface
{
    use CreatedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watchfile:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['watchfile:read', 'watchfile:write'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['watchfile:read', 'watchfile:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: WatchFileStatus::class)]
    #[Groups(['watchfile:read'])]
    private WatchFileStatus $status = WatchFileStatus::NEW;

    #[ORM\Column(type: 'string', enumType: MonitoringType::class, nullable: true)]
    #[Groups(['watchfile:read'])]
    private ?MonitoringType $monitoringType = null;

    /** @var Collection<int, WatchFileActor> */
    #[ORM\OneToMany(mappedBy: 'watchFile', targetEntity: WatchFileActor::class, cascade: ['persist', 'remove'])]
    private Collection $actors;

    /** @var Collection<int, WatchFileUser> */
    #[ORM\OneToMany(mappedBy: 'watchFile', targetEntity: WatchFileUser::class, cascade: ['persist', 'remove'])]
    private Collection $watchFileUsers;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $description, ?User $createdBy)
    {
        $this->name = $name;
        $this->description = $description;
        $this->createdBy = $createdBy;
        $this->actors = new ArrayCollection();
        $this->watchFileUsers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Domain methods with business rules
    public function detectMonitoringType(MonitoringType $type): void
    {
        $this->monitoringType = $type;
        $this->status = WatchFileStatus::MONITORING_TYPE_DETECTED;
    }

    public function archive(): void
    {
        if ($this->status === WatchFileStatus::ARCHIVED) {
            throw new \DomainException('WatchFile is already archived');
        }
        $this->status = WatchFileStatus::ARCHIVED;
    }

    public function enable(): void
    {
        if ($this->status === WatchFileStatus::ARCHIVED) {
            throw new \DomainException('Cannot enable an archived watchfile');
        }
        $this->status = WatchFileStatus::ENABLED;
    }

    // Getters and setters...
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): WatchFileStatus
    {
        return $this->status;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /** @return Collection<int, WatchFileActor> */
    public function getActors(): Collection
    {
        return $this->actors;
    }
}
```

---

## Enums with Methods

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
    case SEARCH_QUERY_GENERATED = 'SEARCH_QUERY_GENERATED';
    case SEARCH_RESULTS_RETRIEVED = 'SEARCH_RESULTS_RETRIEVED';
    case ACTORS_DETECTED = 'ACTORS_DETECTED';
    case SOURCES_DETECTED = 'SOURCES_DETECTED';
    case MONITORING_TYPE_DETECTED = 'MONITORING_TYPE_DETECTED';
    case ENABLED = 'ENABLED';
    case ARCHIVED = 'ARCHIVED';

    public function isActive(): bool
    {
        return $this === self::ENABLED;
    }

    public function canBeArchived(): bool
    {
        return $this !== self::ARCHIVED;
    }

    public function isInProgress(): bool
    {
        return match ($this) {
            self::NEEDS_ANALYZED,
            self::QUESTIONS_GENERATED,
            self::SEARCH_QUERY_GENERATED,
            self::SEARCH_RESULTS_RETRIEVED,
            self::ACTORS_DETECTED,
            self::SOURCES_DETECTED,
            self::MONITORING_TYPE_DETECTED => true,
            default => false,
        };
    }
}
```

---

## Event Dispatcher

```php
// Dispatch domain event
use Psr\EventDispatcher\EventDispatcherInterface;

class WatchFileProcessor
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(): void
    {
        // ... create watchFile
        $this->eventDispatcher->dispatch(new WatchFileCreatedEvent($watchFile, $user));
    }
}

// Event class
readonly class WatchFileCreatedEvent
{
    public function __construct(
        public WatchFile $watchFile,
        public User $user,
    ) {}
}

// Event listener
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class WatchFileCreatedListener
{
    public function __invoke(WatchFileCreatedEvent $event): void
    {
        // Handle event...
    }
}
```

---

## Service Configuration (Trait Injection)

```php
// api/src/Application/WatchFile/GetWatchFileTrait.php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait GetWatchFileTrait
{
    protected WatchFileGatewayInterface $watchFileGateway;

    #[Required]
    public function setWatchFileGateway(WatchFileGatewayInterface $watchFileGateway): void
    {
        $this->watchFileGateway = $watchFileGateway;
    }

    protected function getWatchFile(string $watchFileId): WatchFile
    {
        return $this->watchFileGateway->get($watchFileId);
    }
}
```

---

## Translation Service

```php
use Symfony\Contracts\Translation\TranslatorInterface;

class WatchFileProcessor
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function create(): void
    {
        $defaultName = $this->translator->trans('watch_file.untitled', [], 'messages');
        $watchFile = new WatchFile($defaultName, '', $user);
    }
}
```

---

## Optional Logger Pattern

```php
use Psr\Log\LoggerInterface;

class MyHandler
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function handle(): void
    {
        // Use nullsafe operator
        $this->logger?->info('Processing started', ['id' => $id]);

        try {
            // ...
        } catch (\Exception $e) {
            $this->logger?->error('Processing failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```
