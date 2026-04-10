---
name: backend-models
description: Doctrine entity and database model best practices for PHP/Symfony. Use when creating entities in api/src/Domain/, adding `#[ORM\*]` mapping attributes, including createdAt/updatedAt timestamps, defining foreign key relationships with appropriate cascade behaviors, or adding database constraints (NOT NULL, UNIQUE). Activates when working on `.php` files in Domain layer, choosing appropriate column types, indexing foreign key columns, or implementing validation at both model and database levels.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating new entities in `api/src/Domain/{Entity}/`
- When adding Doctrine ORM mapping attributes (`#[ORM\Entity]`, `#[ORM\Column]`, etc.)
- When including `createdAt` and `updatedAt` timestamps on entities
- When defining relationships with `#[ORM\ManyToOne]`, `#[ORM\OneToMany]`, etc.
- When setting appropriate cascade behaviors (`cascade: ['persist']`, `orphanRemoval: true`)
- When adding database constraints (NOT NULL, UNIQUE, foreign keys)
- When choosing appropriate column types (`uuid`, `string`, `datetime_immutable`, etc.)
- When indexing foreign key columns and frequently queried fields
- When using singular names for entity classes (e.g., `WatchFile`, not `WatchFiles`)
- When balancing normalization with practical query performance needs

# Backend Models

## UUID Primary Key Pattern (Required for All Basil Entities)

Every entity uses UUID as primary key with `UuidGenerator`. This is the standard — do not use `AUTO_INCREMENT` or other strategies:

```php
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;

#[ORM\Id]
#[ORM\Column(type: UuidType::NAME)]
#[ORM\GeneratedValue(strategy: 'CUSTOM')]
#[ORM\CustomIdGenerator(class: UuidGenerator::class)]
private string $id;
```

## Timestamps Pattern (Required on All Entities)

```php
#[ORM\Column(type: 'datetime_immutable')]
private \DateTimeImmutable $createdAt;

#[ORM\Column(type: 'datetime_immutable')]
private \DateTimeImmutable $updatedAt;

public function __construct(...)
{
    $this->createdAt = new \DateTimeImmutable();
    $this->updatedAt = new \DateTimeImmutable();
}

public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
{
    $this->updatedAt = $updatedAt;
}
```

The gateway's `save()` method calls `$entity->setUpdatedAt(new \DateTimeImmutable())` before persisting.

## Enum Column Pattern

```php
#[ORM\Column(type: 'string', enumType: WatchFileStatus::class)]
private WatchFileStatus $status = WatchFileStatus::NEW;
```

## Rules

- **UUID Primary Keys**: Always use `UuidType::NAME` + `UuidGenerator` — never `AUTO_INCREMENT`
- **Clear Naming**: Use singular entity class names (`WatchFile`, not `WatchFiles`); Doctrine derives table name automatically
- **Timestamps**: Include `createdAt` and `updatedAt` on all entities; gateway calls `setUpdatedAt()` on save
- **Data Integrity**: Use database constraints (NOT NULL, UNIQUE, foreign keys) at the database level
- **Indexes**: Index columns used in WHERE/JOIN/ORDER BY: `#[ORM\Index(columns: ['status'])]`
- **Relationship Clarity**: Define cascade behaviors explicitly (`cascade: ['persist']`, `orphanRemoval: true`)
- **Avoid Over-Normalization**: Balance normalization with query performance

## ISO 27001 Compliance

This skill touches security-sensitive areas (A.8.10, A.8.11, A.5.34). Consult the `security-iso27001` skill for applicable controls on data protection, PII handling, and data retention.
