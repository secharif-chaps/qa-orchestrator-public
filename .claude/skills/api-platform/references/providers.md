# API Platform Provider Patterns

Providers fetch data for read operations (GET item and GET collection).

## Directory Structure

```
api/src/Infrastructure/{BoundedContext}/
├── {Entity}Provider.php               # GET item
├── {Entity}CollectionProvider.php     # GET collection
└── ApiFilter/
    ├── UserAccessible{Entity}Filter.php
    ├── {Entity}OrderFilter.php
    └── Only{Criteria}Filter.php
```

## Item Provider

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<WatchFile>
 */
readonly class WatchFileProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<WatchFile> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        private Security $security,
        private EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WatchFile
    {
        /** @var WatchFile|null $result */
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($result === null) {
            return null;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $result;
        }

        // Enrich with computed properties
        return $this->watchFileEnricher->enrich($result, ['user' => $user]);
    }
}
```

## Collection Provider

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<WatchFile>
 */
readonly class WatchFileCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<WatchFile> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private Security $security,
        private EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {}

    /**
     * @return iterable<WatchFile>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        /** @var iterable<WatchFile> $results */
        $results = $this->collectionProvider->provide($operation, $uriVariables, $context);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $results;
        }

        // Enrich each item
        foreach ($results as $watchFile) {
            $this->watchFileEnricher->enrich($watchFile, ['user' => $user]);
        }

        return $results;
    }
}
```

## Custom Provider (No Doctrine)

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileId;

/**
 * @implements ProviderInterface<WatchFile>
 */
readonly class WatchFileProvider implements ProviderInterface
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WatchFile
    {
        $id = WatchFileId::fromString($uriVariables['id']);

        return $this->watchFileGateway->find($id);
    }
}
```

## API Filters

### Access Control Filter

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\User\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class UserAccessibleWatchFileFilter extends AbstractFilter
{
    public function __construct(
        private readonly Security $security,
    ) {
        parent::__construct(null, null, null);
    }

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        // Applied automatically for all queries
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // No user, return nothing
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        // Only return watchfiles the user has access to
        $queryBuilder
            ->innerJoin(sprintf('%s.shares', $alias), 'share')
            ->andWhere('share.user = :currentUser')
            ->setParameter('currentUser', $user->getId());
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
```

### Query Parameter Filter

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class OnlyFavoritesFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($property !== 'favorites') {
            return;
        }

        if ($value !== 'true' && $value !== '1') {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.isFavorite = :isFavorite', $alias))
            ->setParameter('isFavorite', true);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'favorites' => [
                'property' => 'favorites',
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter to show only favorites',
            ],
        ];
    }
}
```

### Order Filter

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class WatchFileOrderFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($property !== 'order') {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $direction = strtoupper($value) === 'ASC' ? 'ASC' : 'DESC';

        $queryBuilder->orderBy(sprintf('%s.createdAt', $alias), $direction);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'order' => [
                'property' => 'order',
                'type' => 'string',
                'required' => false,
                'description' => 'Order by created date (ASC or DESC)',
            ],
        ];
    }
}
```

## Registration in ApiResource

```php
#[ApiResource(
    operations: [
        new Get(
            filters: [UserAccessibleWatchFileFilter::class],
            provider: WatchFileProvider::class,
        ),
        new GetCollection(
            paginationEnabled: true,
            filters: [
                UserAccessibleWatchFileFilter::class,
                WatchFileOrderFilter::class,
                OnlyFavoritesFilter::class,
            ],
            provider: WatchFileCollectionProvider::class,
        ),
    ],
)]
```

## Key Rules

1. **Decorate Default Providers**: Use `#[Autowire]` to inject Doctrine providers
2. **Type Annotations**: Use `@implements ProviderInterface<Entity>`
3. **Readonly Classes**: All providers should be `readonly`
4. **Enrich After Fetch**: Add computed properties after fetching from DB
5. **Access Control in Filters**: Use filters for user-scoped queries
6. **Return Null for Not Found**: Let API Platform handle 404
7. **PHPDoc for Generics**: Document collection return types
