# Doctrine Entity Mapping

## Entity with Attributes

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\User\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'watch_file')]
#[ORM\Index(columns: ['status'], name: 'idx_watch_file_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_watch_file_created_at')]
class WatchFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: WatchFileStatus::class)]
    private WatchFileStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

## UUID Primary Keys

Always use UUID v4 for primary keys:

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class WatchFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    private function __construct()
    {
        $this->id = (string) Uuid::v4();
    }

    public static function create(string $name, User $owner): self
    {
        $watchFile = new self();
        $watchFile->name = $name;
        $watchFile->owner = $owner;
        return $watchFile;
    }
}
```

## Relationships

### ManyToOne (Required)

```php
#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
private User $owner;
```

### ManyToOne (Optional)

```php
#[ORM\ManyToOne(targetEntity: Category::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
private ?Category $category = null;
```

### OneToMany

```php
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
private Collection $documents;

public function __construct()
{
    $this->documents = new ArrayCollection();
}

/**
 * @return Collection<int, Document>
 */
public function getDocuments(): Collection
{
    return $this->documents;
}

public function addDocument(Document $document): void
{
    if (!$this->documents->contains($document)) {
        $this->documents->add($document);
        $document->setWatchFile($this);
    }
}
```

### ManyToMany

```php
#[ORM\ManyToMany(targetEntity: Tag::class)]
#[ORM\JoinTable(name: 'watch_file_tags')]
private Collection $tags;

public function __construct()
{
    $this->tags = new ArrayCollection();
}
```

## Enum Mapping

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum WatchFileStatus: string
{
    case NEW = 'new';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';
}
```

```php
#[ORM\Column(type: Types::STRING, length: 50, enumType: WatchFileStatus::class)]
private WatchFileStatus $status;
```

## JSON Columns

```php
#[ORM\Column(type: Types::JSON)]
private array $metadata = [];

#[ORM\Column(type: Types::JSON, nullable: true)]
private ?array $settings = null;
```

## Custom Doctrine Types

### TranslatedText Type

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Doctrine\Type;

use App\Domain\Shared\TranslatedText;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TranslatedTextType extends Type
{
    public const NAME = 'translated_text';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TranslatedText
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);
        return TranslatedText::create($data['fr'] ?? '', $data['en'] ?? '');
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value->jsonSerialize());
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
```

Register in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        types:
            translated_text: App\Infrastructure\Shared\Doctrine\Type\TranslatedTextType
```

Usage:

```php
#[ORM\Column(type: 'translated_text')]
private TranslatedText $title;
```

## Lifecycle Callbacks

```php
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class WatchFile
{
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

## Embeddables

```php
<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Address
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $street;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $city;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $postalCode;
}
```

Usage in entity:

```php
#[ORM\Embedded(class: Address::class)]
private Address $address;
```

## Key Rules

1. **UUID Primary Keys**: Always use UUID v4, never auto-increment
2. **DateTimeImmutable**: Never use `DateTime`, always `DateTimeImmutable`
3. **Enum Types**: Use PHP 8.1 backed enums with `enumType` parameter
4. **Nullable Explicit**: Always specify `nullable: true` or `nullable: false`
5. **Cascade Carefully**: Only cascade when ownership is clear
6. **Index Important Columns**: Add indexes for frequently queried columns
7. **No Repository Classes**: Use Gateway pattern instead of Doctrine repositories
