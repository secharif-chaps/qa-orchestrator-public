---
name: phpstan
description: PHPStan level 9 static analysis for type safety in PHP/Symfony applications. Use when fixing type errors reported by PHPStan, adding type annotations to parameters/return types, resolving nullable type issues, fixing generic type declarations for Collections and arrays, or ensuring code passes static analysis before commits. Activates when running `task api:phpstan:check`, seeing PHPStan errors in CI/CD, or when type-related issues need resolution. CRITICAL - Always run PHPStan before committing PHP changes to catch type errors early.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When running `task api:phpstan:check` and encountering errors
- When fixing type errors reported by PHPStan in CI/CD pipelines
- When adding type annotations to function parameters and return types
- When resolving nullable type issues (`?string`, `string|null`)
- When fixing generic type declarations (`array<string, mixed>`, `Collection<int, Entity>`)
- When ensuring code quality before committing PHP changes
- When PHPStan reports "Parameter $x has no type declaration"
- When PHPStan reports "Method should return X but returns Y"
- When dealing with Doctrine collection types and generics
- When adding PHPDoc annotations for complex types (`@param`, `@return`, `@var`)

# PHPStan Level 9 Standards

**CRITICAL**: Run `task api:phpstan:check` before committing any PHP changes.

## Basil Configuration

PHPStan config is at `api/phpstan.neon`:

```neon
parameters:
    level: 9
    paths:
        - src
    excludePaths:
        - src/Kernel.php
```

## Running PHPStan

```bash
# Check all files
task api:phpstan:check

# Or directly
docker compose exec api php vendor/bin/phpstan analyse

# Check specific file
docker compose exec api php vendor/bin/phpstan analyse src/Domain/WatchFile/WatchFile.php
```

## Common Fixes

### Missing Return Types

```php
// ❌ PHPStan error: Method has no return type
public function getName()
{
    return $this->name;
}

// ✅ Fixed: explicit return type
public function getName(): string
{
    return $this->name;
}
```

### Nullable Types

```php
// ❌ PHPStan error: Cannot call method on null
$user = $this->userGateway->find($id);
$name = $user->getName(); // $user might be null

// ✅ Fixed: null check
$user = $this->userGateway->find($id);
if ($user === null) {
    throw new UserNotFoundException($id);
}
$name = $user->getName();

// ✅ Or use null coalescing throw
$user = $this->userGateway->find($id)
    ?? throw new UserNotFoundException($id);
```

### Array Types

```php
// ❌ PHPStan error: Array has no value type
private array $items;

// ✅ Fixed: typed array via PHPDoc
/** @var list<WatchFile> */
private array $items;

// ✅ Or in method signature
/** @return list<WatchFile> */
public function getItems(): array
```

### Generic Collections

```php
// ❌ PHPStan error: Generic type missing
public function getDocuments(): Collection
{
    return $this->documents;
}

// ✅ Fixed: generic type annotation
/** @return Collection<int, Document> */
public function getDocuments(): Collection
{
    return $this->documents;
}
```

### Assert Statements

```php
// ❌ PHPStan error: Cannot call method on mixed
public function process(mixed $data): void
{
    $data->getName(); // Error: mixed type
}

// ✅ Fixed: assert the type
public function process(mixed $data): void
{
    assert($data instanceof CreateWatchFileDto);
    $data->getName(); // Now typed
}
```

### Template Types (Generics)

```php
// ❌ PHPStan error: Template type not specified

// ✅ Fixed: use @template annotation
/**
 * @template T of object
 * @param class-string<T> $class
 * @return T|null
 */
public function find(string $class, string $id): ?object
{
    return $this->entityManager->find($class, $id);
}
```

## PHPDoc Annotations

### Array Shapes

```php
/**
 * @return array{
 *     id: string,
 *     name: string,
 *     status: string,
 *     created_at: string,
 * }
 */
public function toArray(): array
```

### Callable Types

```php
/**
 * @param callable(WatchFile): bool $filter
 * @return list<WatchFile>
 */
public function filter(callable $filter): array
```

### Union Types

```php
// PHP 8 native
public function getValue(): string|int|null

// Or via PHPDoc for complex cases
/** @return WatchFile|Document|null */
public function getEntity(): ?object
```

## Basil-Specific Patterns

### Gateway Interface (Real Example)

```php
// api/src/Domain/WatchFile/WatchFileGatewayInterface.php
interface WatchFileGatewayInterface
{
    /**
     * @param string    $id   The ID of the WatchFile
     * @param User|null $user optional user to enrich with virtual properties
     */
    public function get(string $id, ?User $user = null): WatchFile;

    public function save(WatchFile $watchFile): void;

    /**
     * @return list<string> An array of WatchFile IDs
     */
    public function findAllIdsByUser(User $user): array;

    /**
     * Count active WatchFiles for a given user.
     */
    public function countActiveByUserId(string $userId): ResourceCount;
}
```

### Processor with Generic Types

```php
// api/src/Infrastructure/WatchFile/WatchFileProcessor.php
/**
 * @implements ProcessorInterface<UserMessageDto, WatchFile>
 */
class WatchFileProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // $data is typed as UserMessageDto thanks to @implements annotation
        return $watchFile;
    }
}
```

### Provider with Generic Types

```php
// api/src/Infrastructure/WatchFile/WatchFileProvider.php
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

### Action with Array Shape

```php
// api/src/Application/WatchFile/Workflow/ProcessWatchFileClassificationResultAction.php
readonly class ProcessWatchFileClassificationResultAction
{
    /**
     * @param array{
     *     primaryType: string,
     *     confidenceScore: int,
     *     justification: array{en: string, fr: string},
     *     secondaryTypes: list<array{type: string, score: int}>,
     *     explanation: array{keywords: list<string>}
     * } $classification
     */
    public function __construct(
        public string $watchFileId,
        public array $classification,
    ) {}
}
```

### Doctrine QueryBuilder Results

```php
// Type the result properly
/** @var list<string> $result */
$result = $queryBuilder->select('IDENTITY(fu.watchFile)')
    ->from(WatchFileUser::class, 'fu')
    ->getQuery()
    ->getSingleColumnResult();

// Cast scalar results
$count = (int) $queryBuilder
    ->select('COUNT(DISTINCT f.id)')
    ->getQuery()
    ->getSingleScalarResult();
```

### Collection Generic Types

```php
// Doctrine Collections
/** @var Collection<int, WatchFileActor> */
private Collection $actors;

/** @return Collection<int, Document> */
public function getDocuments(): Collection
{
    return $this->documents;
}
```

## Ignoring Errors (Last Resort)

```php
// Only when truly necessary and documented with reason
/** @phpstan-ignore-next-line generics.notSubtype */
$result = $this->legacyMethod();

/* @phpstan-ignore-next-line booleanNot.alwaysFalse Container config varies */
if (!$container->hasParameter('elasticsearch.enabled')) { ... }
```

## Before Committing

```bash
# Run PHPStan
task api:phpstan:check

# Run ECS (code style)
task api:cs:fix

# Run tests
task api:test
```
