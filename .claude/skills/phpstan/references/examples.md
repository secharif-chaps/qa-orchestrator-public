# PHPStan Examples

## Generic Types for API Platform

### Processor with Generic Types

```php
// api/src/Infrastructure/WatchFile/WatchFileProcessor.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\WatchFile\WatchFile;
use App\UserInterface\Dto\Chat\UserMessageDto;

/**
 * @implements ProcessorInterface<UserMessageDto, WatchFile>
 */
class WatchFileProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Thanks to @implements, PHPStan knows $data is UserMessageDto
        // and return type is WatchFile
        return $watchFile;
    }
}
```

### Provider with Generic Types

```php
// api/src/Infrastructure/WatchFile/WatchFileProvider.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\WatchFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<WatchFile>
 */
class WatchFileProvider implements ProviderInterface
{
    /**
     * @var ProviderInterface<WatchFile>
     */
    private readonly ProviderInterface $itemProvider;

    /**
     * @param ProviderInterface<WatchFile> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        ProviderInterface $itemProvider,
    ) {
        /** @var ProviderInterface<WatchFile> $itemProvider */
        $this->itemProvider = $itemProvider;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WatchFile
    {
        /** @var WatchFile|null $result */
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);

        return $result;
    }
}
```

---

## Array Shapes

### Complex Array Shape for Actions

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
     *     explanation: array{
     *         keywords: list<string>,
     *         detectedEntities: list<string>,
     *         userObjective: array{en: string, fr: string}
     *     },
     *     sourceSuggestions: list<string>,
     *     actorSuggestions: list<string>,
     *     deepSearchReadiness: array{
     *         ready: bool,
     *         reason: array{en: string, fr: string},
     *         suggestedSearchQueries: list<string>
     *     }
     * } $classification
     */
    public function __construct(
        public string $watchFileId,
        public array $classification,
    ) {}
}
```

### Return Type Array Shape

```php
/**
 * @return array{
 *     id: string,
 *     name: string,
 *     status: string,
 *     createdAt: string,
 *     updatedAt: string,
 *     actors: list<array{id: string, name: string, type: string}>,
 * }
 */
public function toArray(): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'status' => $this->status->value,
        'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        'actors' => array_map(fn(Actor $a) => [
            'id' => $a->getId(),
            'name' => $a->getName(),
            'type' => $a->getType()->value,
        ], $this->actors->toArray()),
    ];
}
```

---

## Doctrine Collection Types

### Entity Collections

```php
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class WatchFile
{
    /** @var Collection<int, WatchFileActor> */
    #[ORM\OneToMany(mappedBy: 'watchFile', targetEntity: WatchFileActor::class)]
    private Collection $actors;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(mappedBy: 'watchFile', targetEntity: Document::class)]
    private Collection $documents;

    public function __construct()
    {
        $this->actors = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    /** @return Collection<int, WatchFileActor> */
    public function getActors(): Collection
    {
        return $this->actors;
    }

    /** @return Collection<int, Document> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }
}
```

---

## Doctrine QueryBuilder Types

### Typing Query Results

```php
class WatchFileDoctrineGateway implements WatchFileGatewayInterface
{
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
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT f.id)')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'wfu')
            ->where('wfu.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        // Cast scalar result to int
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

---

## Gateway Interface Patterns

### Proper PHPDoc for Gateway Methods

```php
// api/src/Domain/WatchFile/WatchFileGatewayInterface.php
interface WatchFileGatewayInterface
{
    /**
     * Get a WatchFile by ID.
     *
     * @param string    $id   The ID of the WatchFile
     * @param User|null $user Optional user to enrich with virtual properties
     *
     * @throws WatchFileNotFoundException
     */
    public function get(string $id, ?User $user = null): WatchFile;

    /**
     * Persist a WatchFile.
     */
    public function save(WatchFile $watchFile): void;

    /**
     * Find all WatchFile IDs accessible by a user.
     *
     * @return list<string> An array of WatchFile IDs
     */
    public function findAllIdsByUser(User $user): array;

    /**
     * Count active WatchFiles for a user.
     */
    public function countActiveByUserId(string $userId): ResourceCount;

    /**
     * Count non-archived WatchFiles owned by a user.
     */
    public function countNonArchivedByOwnerId(string $userId): int;

    /**
     * Check if an actor relation exists.
     */
    public function hasActorRelation(string $watchFileId, string $actorId): bool;
}
```

---

## Null Handling Patterns

### Null Coalescing with Throw

```php
// Get entity or throw
$watchFile = $this->watchFileGateway->find($id)
    ?? throw new WatchFileNotFoundException($id);

// Null coalescing for defaults
$name = $dto->name ?? 'Default Name';

// Nullsafe operator for optional calls
$this->logger?->info('Processing', ['id' => $id]);
$ownerName = $watchFile->getCreatedBy()?->getDisplayName();
```

### Instanceof Checks

```php
public function process(mixed $data, Operation $operation): mixed
{
    $user = $this->security->getUser();
    if (!$user instanceof User) {
        throw new AccessDeniedHttpException('User not authenticated');
    }

    // Now $user is typed as User
    $userId = $user->getId();
}
```

---

## Ignoring Errors (When Necessary)

```php
// Generic type mismatch (API Platform internals)
/** @phpstan-ignore-next-line generics.notSubtype */
$this->itemProvider = $itemProvider;

// Container parameter that varies by environment
/* @phpstan-ignore-next-line booleanNot.alwaysFalse Container config varies */
if (!$container->hasParameter('elasticsearch.enabled')) {
    return;
}

// Trait property access
/* @phpstan-ignore-next-line function.alreadyNarrowedType */
if ($this->createdBy instanceof User) {
    // ...
}
```

---

## Callable Types

```php
/**
 * @param callable(WatchFile): bool $filter
 * @return list<WatchFile>
 */
public function filterWatchFiles(callable $filter): array
{
    return array_filter($this->watchFiles, $filter);
}

/**
 * @param callable(Document, int): void $callback
 */
public function forEachDocument(callable $callback): void
{
    foreach ($this->documents as $index => $document) {
        $callback($document, $index);
    }
}
```

---

## Template Types (Generics)

```php
/**
 * @template T of object
 * @param class-string<T> $class
 * @return T|null
 */
public function find(string $class, string $id): ?object
{
    return $this->entityManager->find($class, $id);
}

/**
 * @template T of object
 * @param class-string<T> $class
 * @return T
 * @throws EntityNotFoundException
 */
public function get(string $class, string $id): object
{
    return $this->find($class, $id)
        ?? throw new EntityNotFoundException(\sprintf('%s with id %s not found', $class, $id));
}
```

---

## Value Object with Array Shape

```php
// api/src/Domain/Shared/TranslatedText.php
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
}
```
