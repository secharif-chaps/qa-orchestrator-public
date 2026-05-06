# Clean Architecture Examples

## Domain Layer

### Entity with Business Logic

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

#[ORM\Entity]
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
    #[Groups(['watchfile:read', 'watchfile:write'])]
    private string $name;

    #[ORM\Column(type: 'string', enumType: WatchFileStatus::class)]
    #[Groups(['watchfile:read'])]
    private WatchFileStatus $status = WatchFileStatus::NEW;

    #[ORM\Column(type: 'string', enumType: MonitoringType::class, nullable: true)]
    #[Groups(['watchfile:read'])]
    private ?MonitoringType $monitoringType = null;

    /** @var Collection<int, WatchFileActor> */
    #[ORM\OneToMany(mappedBy: 'watchFile', targetEntity: WatchFileActor::class, cascade: ['persist'])]
    private Collection $actors;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $description, ?User $createdBy)
    {
        $this->name = $name;
        $this->createdBy = $createdBy;
        $this->actors = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Domain method with business rule
    public function detectMonitoringType(MonitoringType $type): void
    {
        $this->monitoringType = $type;
        $this->status = WatchFileStatus::MONITORING_TYPE_DETECTED;
    }

    // Domain method enforcing invariants
    public function archive(): void
    {
        if ($this->status === WatchFileStatus::ARCHIVED) {
            throw new WatchFileAlreadyArchivedException($this->id);
        }
        $this->status = WatchFileStatus::ARCHIVED;
    }

    // Domain method with state transition
    public function enable(): void
    {
        if ($this->status === WatchFileStatus::ARCHIVED) {
            throw new \DomainException('Cannot enable an archived watchfile');
        }
        $this->status = WatchFileStatus::ENABLED;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
```

---

### Gateway Interface

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
     * Get a WatchFile by ID, optionally enriched with user-specific properties.
     *
     * @param string    $id   The ID of the WatchFile to retrieve
     * @param User|null $user Optional user to enrich with virtual properties (isFavorite, userRole)
     *
     * @throws Exception\WatchFileNotFoundException
     */
    public function get(string $id, ?User $user = null): WatchFile;

    /**
     * Persist a WatchFile (create or update).
     */
    public function save(WatchFile $watchFile): void;

    /**
     * Get a WatchFile accessible by a specific user.
     *
     * @throws Exception\WatchFileNotFoundException
     */
    public function getForUser(string $id, User $user): WatchFile;

    /**
     * Find all WatchFile IDs accessible by a user.
     *
     * @return list<string> An array of WatchFile IDs
     */
    public function findAllIdsByUser(User $user): array;

    /**
     * Count active (enabled) WatchFiles owned by a user.
     */
    public function countActiveByUserId(string $userId): ResourceCount;

    /**
     * Count non-archived WatchFiles owned by a user.
     */
    public function countNonArchivedByOwnerId(string $userId): int;

    /**
     * Check if an actor relation already exists for this watchfile.
     * Business rule: an actor can only be linked once to a watchfile.
     */
    public function hasActorRelation(string $watchFileId, string $actorId): bool;
}
```

---

### Value Object

```php
// api/src/Domain/Shared/TranslatedText.php
<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Represents a multilingual text value (en/fr).
 * Used for actor names, descriptions, justifications, etc.
 */
readonly class TranslatedText
{
    public function __construct(
        public string $en = '',
        public string $fr = '',
    ) {}

    /**
     * @param array{en?: string, fr?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            en: $data['en'] ?? '',
            fr: $data['fr'] ?? '',
        );
    }

    /**
     * @return array{en: string, fr: string}
     */
    public function toArray(): array
    {
        return ['en' => $this->en, 'fr' => $this->fr];
    }

    public function isEmpty(): bool
    {
        return $this->en === '' && $this->fr === '';
    }

    public function getForLocale(string $locale): string
    {
        return match ($locale) {
            'fr' => $this->fr ?: $this->en,
            default => $this->en ?: $this->fr,
        };
    }
}
```

---

### Domain Enum

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
}
```

---

### Domain Event

```php
// api/src/Domain/WatchFile/Event/WatchFileCreatedEvent.php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Event;

use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

readonly class WatchFileCreatedEvent
{
    public function __construct(
        public WatchFile $watchFile,
        public User $user,
    ) {}
}
```

---

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
     *     secondaryTypes: list<array{type: string, score: int, justification: array{en: string, fr: string}}>,
     *     explanation: array{keywords: list<string>, detectedEntities: list<string>, userObjective: array{en: string, fr: string}},
     *     sourceSuggestions: list<string>,
     *     actorSuggestions: list<string>,
     *     deepSearchReadiness: array{ready: bool, reason: array{en: string, fr: string}, suggestedSearchQueries: list<string>}
     * } $classification
     */
    public function __construct(
        public string $watchFileId,
        public array $classification,
    ) {}
}
```

---

### Handler with Trait

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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AsMessageHandler]
class ProcessWatchFileClassificationResultHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly WatchFileActivityGatewayInterface $activityGateway,
        private readonly WatchFileActivityLogger $activityLogger,
        private readonly AnalysisResultGatewayInterface $analysisResultGateway,
        private readonly NormalizerInterface $normalizer,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ProcessWatchFileClassificationResultAction $action): void
    {
        try {
            $classification = $action->classification;
            $classificationResult = WatchFileTypeClassificationResult::fromArray($classification);

            $watchFile = $this->getWatchFile($action->watchFileId);

            $this->storeClassificationInAnalysisResult($watchFile, $classificationResult);
            $monitoringType = $this->setMonitoringTypeIfConfident($watchFile, $classificationResult);

            $this->watchFileGateway->save($watchFile);
            $this->logClassificationActivity($watchFile, $classificationResult, $monitoringType);

            $this->logger?->info('Classification processed successfully', [
                'watch_file_id' => $watchFile->getId(),
                'monitoring_type' => $monitoringType?->value ?? 'none',
                'confidence' => $classificationResult->primaryType->confidenceScore,
            ]);
        } catch (DomainException $e) {
            $this->logger?->error('Failed to process classification result', [
                'watch_file_id' => $action->watchFileId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function storeClassificationInAnalysisResult(
        WatchFile $watchFile,
        WatchFileTypeClassificationResult $classificationResult,
    ): void {
        $analysisResult = $this->analysisResultGateway->getLastOrCreate($watchFile);
        $analysisResult->setClassificationData($classificationResult);
        $analysisResult->setConfidenceScore($classificationResult->primaryType->confidenceScore);
        $analysisResult->addMetadata('workflow', 'classify-watchfile');
        $analysisResult->addMetadata('timestamp', (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));

        $this->analysisResultGateway->save($analysisResult);
    }

    private function setMonitoringTypeIfConfident(
        WatchFile $watchFile,
        WatchFileTypeClassificationResult $classificationResult,
    ): ?MonitoringType {
        if ($classificationResult->primaryType->confidenceScore >= 60) {
            $monitoringType = $classificationResult->primaryType->type;
            $watchFile->detectMonitoringType($monitoringType);
            return $monitoringType;
        }
        return null;
    }

    private function logClassificationActivity(
        WatchFile $watchFile,
        WatchFileTypeClassificationResult $classificationResult,
        ?MonitoringType $monitoringType,
    ): void {
        $user = $watchFile->getCreatedBy();
        if ($user && $monitoringType) {
            /** @var array<string, mixed> $classificationData */
            $classificationData = $this->normalizer->normalize($classificationResult, 'json');

            $activity = $this->activityLogger->logMonitoringTypeDetection(
                $watchFile,
                $user,
                $monitoringType,
                $classificationData
            );
            $this->activityGateway->save($activity);
        }
    }
}
```

---

### Shared Trait for Handlers

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

## Infrastructure Layer

### Doctrine Gateway Implementation

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
            ->addSelect('fu')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'fu')
            ->where('f.id = :id')
            ->setParameter('id', $id);

        $watchFile = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$watchFile instanceof WatchFile) {
            throw new WatchFileNotFoundException(\sprintf('WatchFile with id %s not found', $id));
        }

        return $this->watchFileEnricher->enrich($watchFile, ['user' => $user]);
    }

    public function save(WatchFile $watchFile): void
    {
        $watchFile->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($watchFile);
        $this->entityManager->flush();
    }

    public function getForUser(string $id, User $user): WatchFile
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('f')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'fu')
            ->where('f.id = :id')
            ->andWhere('fu.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user);

        $watchFile = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$watchFile instanceof WatchFile) {
            throw new WatchFileNotFoundException(\sprintf(
                'WatchFile with id %s is not accessible for user %s',
                $id,
                $user->getId()
            ));
        }

        return $this->watchFileEnricher->enrich($watchFile, ['user' => $user]);
    }

    public function findAllIdsByUser(User $user): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        /** @var list<string> $result */
        $result = $queryBuilder->select('IDENTITY(fu.watchFile)')
            ->from(WatchFileUser::class, 'fu')
            ->where('fu.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }

    public function countActiveByUserId(string $userId): ResourceCount
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $count = $queryBuilder->select('COUNT(DISTINCT f.id)')
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

    public function countNonArchivedByOwnerId(string $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT f.id)')
            ->from(WatchFile::class, 'f')
            ->innerJoin('f.watchFileUsers', 'fu')
            ->where('fu.user = :userId')
            ->andWhere('fu.role = :ownerRole')
            ->andWhere('f.status != :archivedStatus')
            ->setParameter('userId', $userId)
            ->setParameter('ownerRole', WatchFileUserRole::OWNER)
            ->setParameter('archivedStatus', WatchFileStatus::ARCHIVED->value)
            ->getQuery()
            ->getSingleScalarResult();
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
