# API Platform Examples

## Complete ApiResource Definition

```php
// api/src/Domain/WatchFile/WatchFile.php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Infrastructure\WatchFile\ApiFilter\UserAccessibleWatchFileFilter;
use App\Infrastructure\WatchFile\ApiFilter\WatchFileOrderFilter;
use App\Infrastructure\WatchFile\ApiFilter\OnlyFavoritesFilter;
use App\Infrastructure\WatchFile\ApiFilter\IncludeArchivedFilter;
use App\Infrastructure\WatchFile\WatchFileProcessor;
use App\Infrastructure\WatchFile\WatchFileProvider;
use App\Infrastructure\WatchFile\WatchFileCollectionProvider;
use App\Infrastructure\WatchFile\UpdateWatchFileProcessor;
use App\Infrastructure\WatchFile\ChangeWatchFileStatusProcessor;
use App\UserInterface\Dto\Chat\UserMessageDto;

#[ApiResource(
    operations: [
        // GET single item
        new Get(
            openapi: new Operation(
                summary: 'Get a watch file by ID',
                description: 'Retrieves a specific watch file by its unique identifier.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The UUID of the watch file',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                        example: '550e8400-e29b-41d4-a716-446655440000',
                    ),
                ],
            ),
            filters: [UserAccessibleWatchFileFilter::class],
            provider: WatchFileProvider::class
        ),

        // GET collection with pagination and filters
        new GetCollection(
            openapi: new Operation(
                summary: 'Get collection of watch files',
                description: 'Retrieves a paginated list of watch files accessible to the authenticated user.',
            ),
            paginationEnabled: true,
            filters: [
                UserAccessibleWatchFileFilter::class,
                WatchFileOrderFilter::class,
                OnlyFavoritesFilter::class,
                IncludeArchivedFilter::class,
            ],
            provider: WatchFileCollectionProvider::class,
        ),

        // POST with input DTO
        new Post(
            openapi: new Operation(
                summary: 'Create a new watch file with conversation',
                description: 'Creates a new watch file and starts a conversation.',
            ),
            denormalizationContext: ['groups' => ['message:write']],
            input: UserMessageDto::class,
            processor: WatchFileProcessor::class,
        ),

        // PATCH for updates
        new Patch(
            openapi: new Operation(summary: 'Update a watch file'),
            processor: UpdateWatchFileProcessor::class,
        ),

        // Custom sub-resource operation
        new Post(
            uriTemplate: '/watch_files/{id}/status/{status}',
            openapi: new Operation(
                summary: 'Change watch file status',
                description: 'Updates the status of a watch file.',
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true),
                    new Parameter(name: 'status', in: 'path', required: true),
                ],
            ),
            read: false,
            processor: ChangeWatchFileStatusProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['watchfile:read']],
    denormalizationContext: ['groups' => ['watchfile:write']],
)]
class WatchFile
{
    // Entity definition...
}
```

---

## State Provider with Enrichment

```php
// api/src/Infrastructure/WatchFile/WatchFileProvider.php
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
 * Provider that wraps the existing API Platform provider and adds computed properties.
 *
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
        private readonly Security $security,
        private readonly EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {
        /** @var ProviderInterface<WatchFile> $itemProvider */
        $this->itemProvider = $itemProvider;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WatchFile
    {
        /** @var WatchFile|null $result */
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (null === $result) {
            return null;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $result;
        }

        // Enrich with computed properties (isFavorite, userRole, etc.)
        return $this->watchFileEnricher->enrich($result, ['user' => $user]);
    }
}
```

---

## State Processor with Events and Messages

```php
// api/src/Infrastructure/WatchFile/WatchFileProcessor.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Chat\CreateConversationAction;
use App\Application\WatchFile\CheckWatchFileOwnerQuotaAction;
use App\Application\WatchFile\Share\ShareWatchFileAction;
use App\Application\WatchFile\Share\ShareWatchFileBatchAction;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\UserInterface\Dto\Chat\UserMessageDto;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<UserMessageDto, WatchFile>
 */
class WatchFileProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $user = null;
        }

        // Check quota before creating
        if ($user instanceof User) {
            $userId = $user->getId();
            if (null !== $userId) {
                $this->messageBus->dispatch(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
            }
        }

        // Create entity with default values
        $watchFile = new WatchFile(
            $this->translator->trans('watch_file.untitled', [], 'messages'),
            '',
            $user
        );

        $this->watchFileGateway->save($watchFile);

        // Dispatch domain event
        if ($user instanceof User) {
            $this->eventDispatcher->dispatch(new WatchFileCreatedEvent($watchFile, $user));
        }

        // Share with owner via async message
        $createdById = $watchFile->getCreatedBy()?->getId();
        if (\is_string($createdById) && !empty($createdById)) {
            $this->messageBus->dispatch(
                new ShareWatchFileBatchAction(
                    [new ShareWatchFileAction($watchFile->getId(), $createdById, WatchFileUserRole::OWNER)],
                    $createdById,
                    new \DateTimeImmutable(),
                ),
            );
        }

        // Create conversation with user's message
        $this->messageBus->dispatch(
            new CreateConversationAction(watchFileId: $watchFile->getId(), message: $data->content)
        );

        $this->logger?->info('WatchFile created with conversation', [
            'watch_file_id' => $watchFile->getId(),
            'message' => $data->content,
        ]);

        return $watchFile;
    }
}
```

---

## Input DTO with Validation

```php
// api/src/UserInterface/Dto/Chat/UserMessageDto.php
<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Chat;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

readonly class UserMessageDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Message content cannot be empty')]
        #[Assert\Length(min: 1, max: 10000)]
        #[Groups(['message:write'])]
        public string $content,
    ) {}
}
```

---

## Custom API Filter

```php
// api/src/Infrastructure/WatchFile/ApiFilter/UserAccessibleWatchFileFilter.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class UserAccessibleWatchFileFilter extends AbstractFilter
{
    public function __construct(
        private readonly Security $security,
    ) {
        parent::__construct();
    }

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        // This filter is always applied, not based on a property
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // No user = no access to any watchfile
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $userJoinAlias = $queryNameGenerator->generateJoinAlias('watchFileUsers');

        $queryBuilder
            ->innerJoin(\sprintf('%s.watchFileUsers', $rootAlias), $userJoinAlias)
            ->andWhere(\sprintf('%s.user = :currentUser', $userJoinAlias))
            ->setParameter('currentUser', $user);
    }

    public function getDescription(string $resourceClass): array
    {
        return []; // Hidden filter, no description in OpenAPI
    }
}
```

---

## Batch Operation Processor

```php
// api/src/Infrastructure/WatchFile/BatchChangeActorStatusProcessor.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Actor\BatchChangeActorStatusAction;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\UserInterface\Dto\Actor\BatchChangeActorStatusInputDto;
use App\UserInterface\Dto\Actor\BatchChangeActorStatusOutputDto;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<BatchChangeActorStatusInputDto, BatchChangeActorStatusOutputDto>
 */
class BatchChangeActorStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BatchChangeActorStatusOutputDto
    {
        $watchFileId = $uriVariables['id'];
        $watchFile = $this->watchFileGateway->get($watchFileId);

        $this->messageBus->dispatch(new BatchChangeActorStatusAction(
            watchFileId: $watchFileId,
            actorIds: $data->actorIds,
            status: $data->status,
        ));

        return new BatchChangeActorStatusOutputDto(
            watchFileId: $watchFileId,
            updatedCount: count($data->actorIds),
            status: $data->status,
        );
    }
}
```
