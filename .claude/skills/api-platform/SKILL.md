---
name: api-platform
description: API Platform 4.1 for REST API development in PHP/Symfony applications. Use when creating API resources with #[ApiResource], implementing state processors for POST/PUT/PATCH/DELETE operations, creating state providers for GET operations, defining DTOs for input/output transformation, or configuring OpenAPI documentation. Activates when working on files in api/src/Infrastructure/ApiPlatform/, creating #[ApiResource] attributes, or implementing ProcessorInterface/ProviderInterface. CRITICAL - Always use state processors/providers pattern, never controllers for API endpoints.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating new API resources with `#[ApiResource]` attributes
- When implementing state processors for write operations (POST, PUT, PATCH, DELETE)
- When implementing state providers for read operations (GET, GetCollection)
- When creating DTOs for request input or response output
- When configuring API operations (Get, GetCollection, Post, Patch, Delete)
- When adding security rules with `security` attribute on operations
- When working on files in `api/src/Infrastructure/{Entity}/` that end with `Processor.php` or `Provider.php`
- When defining OpenAPI documentation with descriptions and examples
- When implementing custom API filters in `ApiFilter/` directories

# API Platform 4.1 Standards

**CRITICAL**: Use State Processors and Providers, not controllers. Let API Platform handle serialization and validation.

## Basil Directory Structure

```
api/src/
├── Domain/WatchFile/
│   └── WatchFile.php              # Entity with #[ApiResource]
├── Infrastructure/WatchFile/
│   ├── WatchFileProcessor.php      # POST processor
│   ├── UpdateWatchFileProcessor.php
│   ├── ChangeWatchFileStatusProcessor.php
│   ├── WatchFileProvider.php       # GET item provider
│   ├── WatchFileCollectionProvider.php
│   └── ApiFilter/
│       ├── UserAccessibleWatchFileFilter.php
│       ├── WatchFileOrderFilter.php
│       └── OnlyFavoritesFilter.php
└── UserInterface/Dto/
    ├── Chat/UserMessageDto.php
    └── WatchFile/ShareWatchFileInputDto.php
```

## Resource Definition (Real Basil Example)

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
use App\UserInterface\Dto\Chat\UserMessageDto;

#[ApiResource(
    operations: [
        new Get(
            openapi: new Operation(
                summary: 'Get a watchfile by ID',
                description: 'Retrieves a specific watchfile by its unique identifier.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The UUID of the watchfile',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                    ),
                ],
            ),
            filters: [UserAccessibleWatchFileFilter::class],
            provider: WatchFileProvider::class
        ),
        new GetCollection(
            paginationEnabled: true,
            filters: [
                UserAccessibleWatchFileFilter::class,
                WatchFileOrderFilter::class,
                OnlyFavoritesFilter::class,
                IncludeArchivedFilter::class,
            ],
            provider: WatchFileCollectionProvider::class,
        ),
        new Post(
            denormalizationContext: ['groups' => ['message:write']],
            input: UserMessageDto::class,
            processor: WatchFileProcessor::class,
        ),
        new Patch(
            processor: UpdateWatchFileProcessor::class,
        ),
        // Custom sub-resource operation
        new Post(
            uriTemplate: '/watch_files/{id}/status/{status}',
            openapi: new Operation(summary: 'Change watchfile status'),
            read: false,
            processor: ChangeWatchFileStatusProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['watchfile:read']],
    denormalizationContext: ['groups' => ['watchfile:write']],
)]
class WatchFile
{
    // Entity definition
}
```

## State Provider (Real Basil Example)

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

## State Processor (Real Basil Example)

```php
// api/src/Infrastructure/WatchFile/WatchFileProcessor.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Chat\CreateConversationAction;
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

        // Create conversation with message
        $this->messageBus->dispatch(
            new CreateConversationAction(watchFileId: $watchFile->getId(), message: $data->content)
        );

        $this->logger?->info('WatchFile created', [
            'watch_file_id' => $watchFile->getId(),
        ]);

        return $watchFile;
    }
}
```

## Input DTO

```php
<?php

declare(strict_types=1);

namespace App\UserInterface\Dto;

use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateWatchFileDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $name,

        #[EnumConstraint(enumClass: WatchFileType::class)]
        public ?string $type = null,
    ) {}
}
```

## Key Rules

### Let API Platform Handle Validation

```php
// ❌ Bad: manual JSON parsing
$data = json_decode($request->getContent(), true);
Assert::keyExists($data, 'status');

// ✅ Good: use DTO with constraints
#[Post(input: CreateWatchFileDto::class)]
```

### Use EnumConstraint for Choices

```php
// ✅ Good: shared constraint
#[EnumConstraint(enumClass: MonitoringType::class)]
public string $type;

// ❌ Bad: manual check
if (!in_array($type, MonitoringType::cases())) { ... }
```

### Authorization in Processors

Processors do not extend `AbstractController` — `denyAccessUnlessGranted()` is not available. Use `Security::isGranted()` + throw `AccessDeniedHttpException`:

```php
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;

public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
{
    // Check authorization before processing
    if (!$this->security->isGranted(WatchFileVoter::EDIT, $data)) {
        throw new AccessDeniedHttpException('You do not have permission to edit this resource.');
    }

    return $this->handle(new UpdateWatchFileAction($data->getId()));
}
```

## HTTP Status Codes

| Code | Usage                |
| ---- | -------------------- |
| 200  | Success (GET, PATCH) |
| 201  | Created (POST)       |
| 204  | No Content (DELETE)  |
| 400  | Bad Request          |
| 403  | Forbidden            |
| 404  | Not Found            |
| 422  | Validation Error     |

## Documentation

- [processors.md](references/processors.md) - Processor patterns
- [providers.md](references/providers.md) - Provider patterns
- [dto-validation.md](references/dto-validation.md) - DTO and validation
