# Infrastructure Layer Patterns

The Infrastructure layer implements external concerns: persistence, APIs, frameworks.

## Directory Structure

```
api/src/Infrastructure/
├── {BoundedContext}/
│   ├── {Entity}DoctrineGateway.php    # Doctrine implementation
│   ├── {Entity}Processor.php          # API Platform state processor
│   ├── {Entity}Provider.php           # API Platform state provider
│   └── {Entity}ElasticsearchGateway.php  # Search implementation
└── Shared/
    ├── Doctrine/
    │   └── Type/                      # Custom Doctrine types
    ├── Elasticsearch/
    └── Mercure/
```

## Gateway Implementation (Doctrine)

Gateways implement domain interfaces using Doctrine ORM.

### Basic Gateway

IDs are plain `string` (UUID) — there is no `WatchFileId` value object in Basil.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFileStatus;
use Doctrine\ORM\EntityManagerInterface;

// Gateways without HandleTrait CAN be readonly
readonly class WatchFileDoctrineGateway implements WatchFileGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function get(string $id): WatchFile
    {
        $watchFile = $this->entityManager->find(WatchFile::class, $id);
        if (!$watchFile instanceof WatchFile) {
            throw new WatchFileNotFoundException(\sprintf('WatchFile %s not found', $id));
        }

        return $watchFile;
    }

    public function save(WatchFile $watchFile): void
    {
        $watchFile->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($watchFile);
        $this->entityManager->flush();
    }

    public function findByStatus(WatchFileStatus $status): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(WatchFile::class, 'w')
            ->where('w.status = :status')
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getResult();
    }
}
```

### Gateway with Complex Queries

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DocumentDoctrineGateway implements DocumentGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function findByWatchFileWithPagination(
        string $watchFileId,
        int $page = 1,
        int $limit = 20,
    ): array {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->where('d.watchFile = :watchFileId')
            ->setParameter('watchFileId', $watchFileId)
            ->orderBy('d.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByWatchFile(string $watchFileId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.watchFile = :watchFileId')
            ->setParameter('watchFileId', $watchFileId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
```

## API Platform Provider

Providers fetch data for API Platform resources.

### Collection Provider

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\User\UserId;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class WatchFileCollectionProvider implements ProviderInterface
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();

        return $this->watchFileGateway->findByOwner($user->getId());
    }
}
```

### Item Provider

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileId;

readonly class WatchFileItemProvider implements ProviderInterface
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        return $this->watchFileGateway->get($uriVariables['id']);
    }
}
```

## API Platform Processor

Processors handle write operations (POST, PUT, PATCH, DELETE).

### Create Processor

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\CreateWatchFileAction;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

// ⚠️ NOT readonly — HandleTrait is incompatible with readonly class
class WatchFileCreateProcessor implements ProcessorInterface
{
    use HandleTrait;

    // ⚠️ Do NOT use constructor promotion for $messageBus with HandleTrait
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
            name: $data->getName(),
            ownerId: $user->getId(),
            type: $data->getType(),
        ));
    }
}
```

### Update Processor

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

class WatchFileUpdateProcessor implements ProcessorInterface
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

### Delete Processor

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\DeleteWatchFileAction;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class WatchFileDeleteProcessor implements ProcessorInterface
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

## NullGateway for Testing

Use NullGateway (in-memory implementation) for unit tests.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\User\UserId;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileId;
use App\Domain\WatchFile\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFileStatus;

final class NullWatchFileGateway implements WatchFileGatewayInterface
{
    /** @var array<string, WatchFile> */
    private array $storage = [];

    public function find(WatchFileId $id): ?WatchFile
    {
        return $this->storage[(string) $id] ?? null;
    }

    public function get(WatchFileId $id): WatchFile
    {
        return $this->find($id)
            ?? throw new WatchFileNotFoundException($id);
    }

    public function save(WatchFile $watchFile): void
    {
        $this->storage[(string) $watchFile->getId()] = $watchFile;
    }

    public function delete(WatchFile $watchFile): void
    {
        unset($this->storage[(string) $watchFile->getId()]);
    }

    public function findByOwner(UserId $ownerId): array
    {
        return array_filter(
            $this->storage,
            fn (WatchFile $wf) => (string) $wf->getOwner()->getId() === (string) $ownerId,
        );
    }

    public function findByStatus(WatchFileStatus $status): array
    {
        return array_filter(
            $this->storage,
            fn (WatchFile $wf) => $wf->getStatus() === $status,
        );
    }

    // Test helpers
    public function addFixture(WatchFile $watchFile): void
    {
        $this->save($watchFile);
    }

    public function clear(): void
    {
        $this->storage = [];
    }
}
```

## Service Configuration

Only gateway interface aliases need to be declared in `config/services.yaml`. API Platform providers/processors are **auto-detected** via their interfaces with Symfony's autoconfiguration — no need to add tags manually.

```yaml
services:
    # Gateway interface → implementation binding
    App\Domain\WatchFile\WatchFileGatewayInterface:
        alias: App\Infrastructure\WatchFile\WatchFileDoctrineGateway
```

## Key Rules

1. **Implement Domain Interfaces**: Gateways must implement domain gateway interfaces
2. **Use HandleTrait**: Use `Symfony\Component\Messenger\HandleTrait`; dispatch actions, never call handlers directly
3. **Readonly Rules**: Gateways and providers CAN be `readonly`. Processors using `HandleTrait` MUST NOT be `readonly class`.
4. **String IDs**: Use plain `string` for IDs — there is no `WatchFileId`/`UserId` value object in Basil
5. **No Business Logic**: Infrastructure handles technical concerns only
6. **NullGateway for Tests**: Provide in-memory implementations for unit testing
7. **Security in Providers**: Use `$this->security->isGranted()` + throw `AccessDeniedHttpException`
8. **Provider vs Processor**: Providers read, Processors write
