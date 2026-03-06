# Domain Layer Patterns

The Domain layer contains the core business logic, completely isolated from frameworks and external concerns.

## Directory Structure

```
api/src/Domain/
├── {BoundedContext}/
│   ├── {Entity}.php                    # Aggregate root
│   ├── {Entity}Id.php                  # Value Object (UUID)
│   ├── {Entity}GatewayInterface.php    # Repository contract
│   ├── {Entity}Status.php              # Enum for states
│   ├── {Entity}NotFoundException.php   # Domain exception
│   └── {ValueObject}.php               # Other value objects
└── Shared/
    ├── TranslatedText.php              # Reusable value objects
    └── DomainException.php             # Base exception
```

## Entity Pattern

Entities are aggregate roots with identity and lifecycle. Use private constructor with named static factory methods.

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\User\User;
use App\Domain\User\UserId;

class WatchFile
{
    private function __construct(
        private WatchFileId $id,
        private string $name,
        private User $owner,
        private WatchFileStatus $status,
        private \DateTimeImmutable $createdAt,
        private ?UserId $archivedBy = null,
        private ?\DateTimeImmutable $archivedAt = null,
    ) {}

    // Named constructor for creation
    public static function create(string $name, User $owner): self
    {
        return new self(
            id: WatchFileId::generate(),
            name: $name,
            owner: $owner,
            status: WatchFileStatus::NEW,
            createdAt: new \DateTimeImmutable(),
        );
    }

    // Domain behavior that enforces business rules
    public function archive(UserId $archivedBy): void
    {
        if ($this->status === WatchFileStatus::ARCHIVED) {
            throw new WatchFileAlreadyArchivedException($this->id);
        }

        $this->status = WatchFileStatus::ARCHIVED;
        $this->archivedBy = $archivedBy;
        $this->archivedAt = new \DateTimeImmutable();
    }

    public function rename(string $newName): void
    {
        if (empty(trim($newName))) {
            throw new \InvalidArgumentException('WatchFile name cannot be empty');
        }

        $this->name = $newName;
    }

    // Getters
    public function getId(): WatchFileId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getStatus(): WatchFileStatus
    {
        return $this->status;
    }
}
```

## Value Object Pattern

Value Objects are immutable, compared by value, and self-validating.

### ID Value Object

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Symfony\Component\Uid\Uuid;

readonly class WatchFileId implements \Stringable
{
    private function __construct(
        private Uuid $value,
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::v4());
    }

    public static function fromString(string $id): self
    {
        return new self(Uuid::fromString($id));
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
```

### Complex Value Object

```php
<?php

declare(strict_types=1);

namespace App\Domain\Shared;

readonly class TranslatedText implements \JsonSerializable
{
    private function __construct(
        private string $fr,
        private string $en,
    ) {}

    public static function create(string $fr, string $en): self
    {
        return new self($fr, $en);
    }

    public static function fromLocale(string $text, string $locale): self
    {
        return match ($locale) {
            'fr' => new self($text, ''),
            'en' => new self('', $text),
            default => throw new \InvalidArgumentException("Unsupported locale: {$locale}"),
        };
    }

    public function get(string $locale): string
    {
        return match ($locale) {
            'fr' => $this->fr,
            'en' => $this->en,
            default => $this->en,
        };
    }

    public function jsonSerialize(): array
    {
        return ['fr' => $this->fr, 'en' => $this->en];
    }
}
```

## Enum Pattern

Use PHP 8.1+ enums for finite states.

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum WatchFileStatus: string
{
    case NEW = 'new';
    case NEEDS_ANALYZED = 'needs_analyzed';
    case QUESTIONS_GENERATED = 'questions_generated';
    case SEARCH_QUERY_GENERATED = 'search_query_generated';
    case SEARCH_RESULTS_RETRIEVED = 'search_results_retrieved';
    case ACTORS_DETECTED = 'actors_detected';
    case SOURCES_DETECTED = 'sources_detected';
    case MONITORING_TYPE_DETECTED = 'monitoring_type_detected';
    case ARCHIVED = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::NEW => $target === self::NEEDS_ANALYZED,
            self::NEEDS_ANALYZED => $target === self::QUESTIONS_GENERATED,
            // ... define valid transitions
            default => false,
        };
    }
}
```

## Gateway Interface Pattern

Gateway interfaces define the contract for persistence. They live in Domain but are implemented in Infrastructure.

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

interface WatchFileGatewayInterface
{
    /**
     * Find by ID, returns null if not found.
     */
    public function find(WatchFileId $id): ?WatchFile;

    /**
     * Get by ID, throws exception if not found.
     *
     * @throws WatchFileNotFoundException
     */
    public function get(WatchFileId $id): WatchFile;

    /**
     * Persist the entity.
     */
    public function save(WatchFile $watchFile): void;

    /**
     * Remove the entity.
     */
    public function delete(WatchFile $watchFile): void;

    /**
     * Find all watchfiles owned by a user.
     *
     * @return WatchFile[]
     */
    public function findByOwner(UserId $ownerId): array;

    /**
     * Find watchfiles with specific status.
     *
     * @return WatchFile[]
     */
    public function findByStatus(WatchFileStatus $status): array;
}
```

## Domain Exception Pattern

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\DomainException;

final class WatchFileNotFoundException extends DomainException
{
    public function __construct(WatchFileId $id)
    {
        parent::__construct(
            message: sprintf('WatchFile with ID "%s" not found', $id),
            code: 404,
        );
    }
}
```

## Key Rules

1. **No Framework Dependencies**: Domain layer must not import Symfony, Doctrine, or any framework
2. **No Infrastructure Imports**: Never import from `App\Infrastructure\*`
3. **Self-Contained**: All business rules live in entities and value objects
4. **Immutable Value Objects**: Use `readonly` classes for value objects
5. **Named Constructors**: Prefer `static` factory methods over public constructors
6. **Explicit State Transitions**: Validate state changes in entity methods
7. **Domain Exceptions**: Create specific exceptions for domain errors
