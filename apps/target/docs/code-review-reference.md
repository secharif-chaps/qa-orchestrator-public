# Code Review Reference v1.0

## ⚡ Quick Review Checklist (Top 10)

Before diving into layer-specific rules, verify these critical points:

- [ ] No Infrastructure imports in the Domain layer
- [ ] Every handler is covered by unit **and** integration tests
- [ ] Actions implement the shared action interfaces
- [ ] Expensive operations avoid N+1 queries (batching applied)
- [ ] Domain exceptions (not SPL) are thrown across layers
- [ ] Messenger dispatch goes through `HandleTrait`/equivalent helper
- [ ] Translation keys follow underscore-notation naming
- [ ] Configuration values come from env/parameters, not literals
- [ ] Database migrations are symmetric (matching `up`/`down`)
- [ ] Documentation for every new/updated API endpoint is committed alongside implementation

## Cross-Layer Standards

### Configuration & Environment

- **Config from env**: Expose tunables (timeouts, limits, hostnames) through env/config instead of literals so deployments remain configurable.

```yaml
# ✅ Good
parameters:
  app.document_quota: '%env(int:DOCUMENT_QUOTA)%'

# ❌ Bad
private const DOCUMENT_QUOTA = 25;
```

- **Explicit locale configuration**: Inject supported locales via configuration parameters; never rely on default arrays or hard-coded lists.

```yaml
# config/services.yaml
parameters:
    app.supported_locales: ['en', 'fr']
```

### Translation Keys

- **Underscore-notation translation keys**: Keep translation catalogs flat with underscore-separated keys so frontend and backend stay aligned.

```json
// ✅ Good
{
  "quota.limit_reached": "Quota reached"
}

// ❌ Bad: mixed separators
{
  "quotaLimitReached": "Quota reached"
}
```

- **Enum-owned translation keys**: Expose `getTranslationKey()` directly on enums so adding a case forces adding its translation.

```php
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';

    public function translationKey(): string
    {
        return 'document_status.' . $this->value;
    }
}
```

- **No runtime key concatenation**: Build translation keys statically so missing keys are spotted at review time.

```php
// ❌ Bad
$key = sprintf('quota.%s_limit', $type);
$translator->trans($key);

// ✅ Good
$translator->trans('quota.watchfile_limit');
```

### Code Quality

- **Constructor promotion preferred**: Promote dependencies directly in constructors to reduce boilerplate.

```php
readonly class Handler
{
    public function __construct(private readonly Gateway $gateway) {}
}
```

- **Guard clauses over nesting**: Prefer early returns to keep methods linear and reduce cyclomatic complexity.

```php
// ❌ Bad: nested conditions
if ($watchFile) {
    if ($watchFile->isEnabled()) {
        $this->process($watchFile);
    } else {
        throw new DomainException('Disabled');
    }
} else {
    throw new DomainException('Not found');
}

// ✅ Good: guard clauses
if (!$watchFile) {
    throw new DomainException('Not found');
}
if (!$watchFile->isEnabled()) {
    throw new DomainException('Disabled');
}
$this->process($watchFile);
```

- **No pass-by-reference**: Avoid mutating parameters by reference; return explicit values.

```php
// ❌ Bad
private function hydrate(array &$data): void { $data['flag'] = true; }

// ✅ Good
private function hydrate(array $data): array
{
    $data['flag'] = true;
    return $data;
}
```

- **Prefer null-coalescing assignments**: Use `??=` to make defaulting explicit and atomic.

```php
$watchFileLimit ??= $this->defaultLimit;
```

- **Iterator count upfront**: Capture count before consumption or increment manually.

```php
$count = iterator_count($actors); // count first
rewind($actors);
```

### Validation & Assertions

- **Webmozart assertions toolkit**: Leverage full `webmozart/assert` helpers for consistent, debuggable exceptions.

```php
// ✅ Assert::uuid() provides clear DomainException
Assert::uuid($action->watchFileId);
Assert::isArray($filters);
Assert::keyExists($data, 'status');
```

- **Shared validation exceptions**: Reuse dedicated config validation exceptions instead of raw `InvalidArgumentException`.

```php
throw new InvalidQuotaConfiguration('Quota must be positive.');
```

- **Filter value validation**: Validate filter inputs (UUIDs, ranges) explicitly; never rely on implicit casting.

```php
foreach ($request->query->all('watchFileIds') as $id) {
    Assert::uuid($id);
}
```

### Exception Handling

- **Bubble original errors**: When rethrowing, attach the original exception to keep stack traces intact.

```php
// ❌ Bad: original exception lost
try {
    $this->repository->save($entity);
} catch (\Throwable $exception) {
    throw new DomainException('Unable to save entity');
}

// ✅ Good: preserve stack trace
try {
    $this->repository->save($entity);
} catch (\Throwable $exception) {
    throw new DomainException('Unable to save entity', previous: $exception);
}
```

- **Avoid redundant rethrow**: Remove catch blocks that merely rethrow; add context or let exceptions bubble.

```php
// ❌ Bad: useless catch
try {
    $this->client->index($payload);
} catch (\Throwable $exception) {
    throw $exception;
}

// ✅ Good: direct call
$this->client->index($payload);
```

### Logging

- **Mandatory loggers with NullLogger fallback**: Type-hint `LoggerInterface` (non-null) and wire `NullLogger` in tests.

```php
class QuotaChecker
{
    public function __construct(private LoggerInterface $logger) {}
}

// tests
$checker = new QuotaChecker(new NullLogger());
```

### Shared Patterns

- **Updated-by trait reuse**: When an entity tracks the last updater, reuse the shared interface/trait pattern.

```php
class Document implements TracksUpdatedByInterface
{
    use TracksUpdatedByTrait;
}
```

## Domain Layer

### 🔴 Critical Rules

- **Domain isolation**: Keep domain entities framework-agnostic; no Symfony, API Platform, or Doctrine specifics beyond unavoidable attributes.

```php
// ❌ Bad: domain entity depends on Doctrine metadata
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Document { /* ... */ }

// ✅ Good: pure domain model
readonly class Document
{
    public function __construct(private Uuid $id, private string $title) {}
}
```

- **No infrastructure imports**: Domain code must never `use App\Infrastructure\…`.

```php
// ❌ Bad
use App\Infrastructure\Doctrine\Entity\WatchFileEntity;

// ✅ Good
use App\Domain\WatchFile\WatchFile;
```

- **Explicit owner relationships**: Determine ownership via dedicated relations, not creator metadata.

```php
class WatchFileUser
{
    public function __construct(
        private readonly WatchFile $watchFile,
        private readonly User $user,
        private readonly WatchFileUserRole $role,
    ) {}
}
```

- **Compare by identifiers**: Always compare aggregates via immutable identifiers.

```php
if ($document->getOwnerId()->equals($userId)) {
    // ...
}
```

- **Missing aggregate is critical**: Treat missing relations as errors with domain exceptions and error-level logging.

```php
// ❌ Bad: simple warning, execution continues with invalid state
if (null === $document->getWatchFile()) {
    $this->logger?->warning('Document sans watchfile');
    return;
}

// ✅ Good: domain exception + error log for observability
$watchFile = $document->getWatchFile()
    ?? throw new DocumentHasNoWatchFileException($document->getId());
$this->logger->error('Document without watchfile', ['document_id' => $document->getId()]);
```

- **Manual validation invariants**: Never use public setters; funnel changes through dedicated methods that update all fields atomically.

```php
// ❌ Bad: public setters break invariants
$document->setManualStatus($status);
$document->setValidatedBy($user);
$document->setValidatedAt(new DateTimeImmutable());

// ✅ Good: encapsulation via dedicated method
$document->manuallyValidate(
    status: ManualValidationStatus::ACCEPTED,
    validatedBy: $user,
    validatedAt: new DateTimeImmutable(),
);
```

- **Do not fabricate IDs**: Never invent UUIDs for missing relations; log or raise an exception.

```php
// ❌ Bad
$document->setWatchFileId(Uuid::v4());

// ✅ Good
throw new DocumentHasNoWatchFileException($document->getId());
```

### 🟡 Important Rules

- **Nullsafe relation traversal**: Chain nullsafe calls when drilling into related entities.

```php
$ownerName = $watchFile->getOwner()?->getUser()?->getDisplayName();
```

- **Prefer Uuid value objects**: Model identifiers as `Uuid` value objects for type safety.

```php
readonly class WatchFileId
{
    public function __construct(private Uuid $value) {}
}
```

- **Domain enums use neutral values**: Enum case values describe business concepts, not config paths.

```php
enum ActorType: string
{
    case Person = 'person';
    case Organization = 'organization';
}
```

- **Domain exceptions over SPL**: Throw domain-specific exceptions extending the shared base hierarchy.

```php
// ✅ Good: domain exception in handler
throw new ActorQuotaExceeded($watchFileId);

// ❌ Bad: HTTP exception in handler
throw new BadRequestHttpException('Invalid quota');
```

- **Domain owns its ID**: Let aggregates generate their IDs internally.

```php
private function __construct(private readonly WatchFileId $id) {}

public static function create(...): self
{
    return new self(WatchFileId::generate(), ...);
}
```

- **Domain validation methods**: Add methods that update timestamps/statuses together.

```php
public function archive(UserId $actor): void
{
    $this->status = WatchFileStatus::ARCHIVED;
    $this->archivedBy = $actor;
    $this->archivedAt = new DateTimeImmutable();
}
```

- **Provide domain fallbacks**: When derived data is missing, fall back to aggregate-level defaults.

```php
public function effectiveSubject(): string
{
    return $this->customSubject ?? $this->watchFile->getSubject();
}
```

- **Encapsulate derived updates**: Expose domain methods for multi-field updates.

```php
public function syncSubjectFromWatchFile(WatchFile $watchFile): void
{
    $this->subject = $watchFile->getSubject();
    $this->subjectVersion = $watchFile->getSubjectVersion();
}
```

- **Return existing aggregates**: When a handler mutates an aggregate, return the same instance.

```php
public function __invoke(AddActorAction $action): WatchFile
{
    $watchFile = $this->watchFileGateway->get($action->watchFileId);
    // mutate
    return $watchFile;
}
```

- **Domain-specific gateways**: Split gateway interfaces per responsibility.

```php
interface DocumentGateway
{
    public function get(DocumentId $id): Document;
}

interface DocumentQuotaGateway
{
    public function countByOwner(UserId $id): int;
}
```

### 🟢 Best Practices

- **Readonly DTO/value objects**: Declare DTOs/VOs as `readonly` to highlight immutability.

```php
readonly class ActorSnapshot
{
    public function __construct(public ActorId $id, public string $label) {}
}
```

- **Document audit entities**: Document audit entities with class-level PHPDoc.

```php
/**
 * Immutable snapshot of a manual validation decision.
 */
class DocumentValidation { /* ... */ }
```

- **Normalize entity names**: Apply normalization when deduplicating names.

```php
$normalized = $this->inflector->normalize($actorName);
```

- **Enum-based fallbacks**: Use enum helpers for defaults.

```php
$type = $action->type ?? ActorType::default();
```

- **Strongly typed event DTOs**: Represent event payloads with dedicated DTOs.

```php
class DocumentCreatedEvent
{
    public function __construct(public DocumentId $id, public WatchFileId $watchFileId) {}
}
```

## Application Layer

- **Integration coverage required**: Add integration tests when use-cases span multiple layers.

```php
// tests/Integration/WatchFile/CreateWatchFileTest.php
$client->request('POST', '/api/watch_files', [...]);
$this->assertResponseIsSuccessful();
```

- **Actions implement interfaces**: Command/query DTOs must implement shared action interfaces.

```php
readonly class CreateWatchFileAction implements SyncActionInterface
{
    public function __construct(public WatchFileId $id) {}
}
```

- **No final action classes**: Keep action/handler classes extensible; use `readonly` for immutability.

```php
readonly class AddActorAction
{
    public function __construct(public WatchFileId $watchFileId, public ActorId $actorId) {}
}
```

- **Messenger dispatch consistency**: Always use `HandleTrait::handle()` or `MessageBusInterface::dispatch()`.

```php
// ✅ Good: via HandleTrait
use HandleTrait;
$this->handle(new ResetManualValidationAction($documentId));

// ✅ Good: via MessageBus
$this->messageBus->dispatch(new TriggerSummaryGenerationAction($documentId));

// ❌ Bad: direct handler invocation
$this->resetValidationHandler->__invoke($action);
```

- **Action DTO carries context**: Pass every required bit of context via the action object.

```php
readonly class ValidateDocumentAction
{
    public function __construct(
        public DocumentId $documentId,
        public UserId $validatedBy,
        public Locale $locale,
    ) {}
}
```

- **Guard conditions before handlers**: Validate and authorize before handlers.

```php
if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
    throw new AccessDeniedHttpException();
}

$this->handle(new UpdateWatchFileAction($watchFile->getId(), ...));
```

- **Differentiate empty vs error**: Propagate exceptions for technical failures.

```php
try {
    return $this->actorGateway->search($filters);
} catch (ElasticsearchException $exception) {
    throw new ActorSearchFailed($exception->getMessage(), previous: $exception);
}
```

- **Return structured outcomes**: Use typed output DTOs with success/failure metadata.

```php
readonly class CreateWatchFileResult
{
    public function __construct(public bool $created, public ?WatchFileId $id) {}
}
```

- **Reuse existing handlers**: Invoke existing action/handler pairs instead of duplicating orchestration.

```php
// ❌ Bad
$this->addActorHandler->__invoke(new AddActorAction(...));

// ✅ Good
$this->handle(new AddActorAction(...));
```

- **Centralize state transitions**: Route every state change through dedicated reusable action/handler pairs.

```php
// ✅ Good: dedicated action for reuse
readonly class ChangeStatusAction implements SyncActionInterface
{
    public function __construct(public AggregateId $id, public Status $status) {}
}

// ❌ Bad: inline mutation
$aggregate->setStatus(Status::DISABLED);

// ✅ Good: dispatch the transition action
$this->handle(new ChangeStatusAction($aggregateId, Status::DISABLED));
```

- **Emit domain events after validation**: Publish domain events after key validation steps.

```php
$this->eventBus->dispatch(new DocumentManuallyValidated($documentId));
```

- **Extract orchestration into handlers**: Replace large private methods with dedicated actions/handlers.

```php
// ❌ Bad: private method hidden inside controller
private function triggerWorkflow(Aggregate $aggregate): void { /* ... */ }

// ✅ Good: orchestration extracted in reusable handler
$this->handle(new TriggerWorkflowAction($aggregate->getId()));
```

## Infrastructure & Integration

### Database & Performance

- **Relation-based counts**: When enforcing quotas, join the actual ownership relation instead of relying on metadata.

```php
$qb
    ->innerJoin('aggregate.memberships', 'membership')
    ->where('membership.user = :userId')
    ->andWhere('membership.role = :requiredRole');
```

- **Batch database operations**: Collect IDs and perform single queries; flush entities in batches to avoid N+1.

```php
// Batch lookups
$ids = array_map(fn ($row) => $row['aggregate_id'], $rows);
$entities = $repository->findBy(['id' => $ids]);

// Batch persistence
foreach ($entities as $index => $entity) {
    $em->persist($entity);
    if (0 === $index % 50) {
        $em->flush();
        $em->clear();
    }
}
```

- **Cache repository lookups**: Build associative maps before iterating.

```php
$entityMap = [];
foreach ($entities as $entity) {
    $entityMap[$entity->getId()->toString()] = $entity;
}
```

- **Prefer maps over nested loops**: Build lookup tables to avoid quadratic loops.

```php
$actorsById = array_column($actors, null, 'id');
foreach ($events as $event) {
    $event->attachActor($actorsById[$event->actorId] ?? null);
}
```

- **Drop redundant DISTINCT**: Remove unnecessary `DISTINCT` when no joins duplicate rows.

```php
$qb->select('source.id'); // no DISTINCT needed
```

### Gateway & Serialization

- **Gateway specialization**: Keep each aggregate's queries/counters inside its dedicated gateway.

```php
class DoctrineAggregateGateway implements AggregateGatewayInterface
{
    public function countPending(): int { /* ... */ }
}
```

- **DateTime best practices**: Pass `DateTimeImmutable` through serializers; parse inbound strings with validation.

```php
// Serialization
$payload['createdAt'] = $document->getCreatedAt(); // let serializer format

// Parsing
$date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $input);
Assert::isInstanceOf($date, DateTimeImmutable::class);
```

### Exception Handling

- **Specific infra exception handling**: Catch exact client exception types and wrap them with context.

```php
try {
    $this->esClient->index($payload);
} catch (ElasticsearchException $exception) {
    throw new DocumentIndexingFailed(
        "Failed to index document {$documentId}",
        previous: $exception
    );
}
```

### Migrations & Configuration

- **Symmetric migrations**: Ensure every migration's `down()` truly reverts the `up()`.

```php
public function down(Schema $schema): void
{
    $schema->dropTable('aggregate_snapshot');
}
```

- **Configurable magic numbers**: Expose timeouts, retries, and queue sizes via configuration.

```yaml
app:
    http_client_timeout: '%env(int:HTTP_TIMEOUT)%'
```

## User Interface & API Platform

- **Translator locale auto-detection**: Rely on Symfony's locale negotiation instead of manual parsing.

```php
// ❌ Bad: manual header parsing
$locale = $request->headers->get('Accept-Language');

// ✅ Good: let Symfony handle it
public function __construct(private readonly LocaleResolver $localeResolver) {}
```

- **Stable response API usage**: Interact with API Platform responses via `$client->request()` return values.

```php
// ✅ Good: use response object directly
$response = $client->request('GET', '/api/resources');
$data = $response->toArray();
```

- **Controlled error payload keys**: Emit a single, predictable error key.

```php
return new JsonResponse(['detail' => 'Resource not found'], 404);
```

- **Document API endpoints**: Keep API Platform resource docs up-to-date.

```php
#[ApiResource(
    description: 'Manages aggregate resources with CRUD operations',
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('CREATE')"),
    ]
)]
```

- **Symfony validator, not manual JSON**: Let API Platform handle deserialization/validation.

```php
// ❌ Bad: manual parsing
$data = json_decode($request->getContent(), true);
Assert::keyExists($data, 'status');

// ✅ Good: let API Platform deserialize + validate
#[Post(input: CreateResourceDto::class)]
```

- **API Platform serialization pipeline**: Keep `deserialize`/`validate` enabled by default.

```php
#[Post(
    deserialize: true,  // ✅ Default behavior
    validate: true,     // ✅ Default behavior
)]
```

- **EnumConstraint for DTO choices**: Use shared Enum constraint instead of `in_array` checks.

```php
use App\Infrastructure\Shared\Constraint\EnumConstraint;

#[EnumConstraint(enumClass: ResourceStatus::class)]
public string $status;
```

- **Processor-level auth checks**: Enforce authorization in processors before dispatching actions.

```php
public function process(mixed $data, Operation $operation, array $uriVariables = []): mixed
{
    $this->denyAccessUnlessGranted('EDIT', $data);
    return $this->handle(new UpdateResourceAction($data->getId()));
}
```

- **Security grant for target user**: Use `Security::isGrantedForUser()` when verifying another user's permissions.

```php
if (!$this->security->isGrantedForUser($targetUser, 'EDIT', $resource)) {
    throw new AccessDeniedHttpException();
}
```

- **Specific not-found messages**: Mention the missing identifier in errors.

```php
throw new NotFoundHttpException("Resource {$resourceId} not found");
```

## Testing & Quality

### Test Architecture

- **Integration = HTTP + DB**: Functional tests must go through `ApiTestCase` clients hitting real endpoints and database.

- **Unit tests never boot the Kernel**: Keep unit suites pure PHP; kernel boots are reserved for functional tests.

```php
// ❌ Bad: kernel boot unnecessary in unit test
static::bootKernel();
$handler = static::getContainer()->get(CheckDocumentQuotaHandler::class);

// ✅ Good: direct instantiation
$handler = new CheckDocumentQuotaHandler($gateway, $config, $logger);
```

### Test Coverage

- **Error-path test coverage**: Add tests for infrastructure failures (search engine down, missing aggregates, corrupted payloads).

- **Creation tests need positive case**: Each suite must contain at least one "happy path" creation test before asserting failure flows.

- **Validation edge-case tests**: Cover boundary values (e.g., confidence scores 0/100/101).

### Test Patterns

- **Avoid `_real()` in tests**: Work with persisted fixtures/IDs instead of calling helper methods.

- **Real iterators in tests**: Pass real iterators (e.g., `new ArrayIterator`) when code expects `Traversable`.

- **Avoid domain entity mocks**: Instantiate real domain entities so invariants remain enforced.

- **Use concrete users in tests**: Prefer real `User` entities over mocking `UserInterface`.

- **Use real logger defaults**: Pass `NullLogger` in tests rather than mocking the logger.

- **Domain assertions belong in domain tests**: Keep domain behavior assertions within domain test suites, not inside application handler tests.

- **Foundry 2.7 auto-refresh with lazy objects**: Foundry 2.7 introduces PHP 8.4 lazy objects for entity auto-refresh, replacing the deprecated Proxy mechanism. Avoid using `_real()`, `_enableAutoRefresh()`, and `_disableAutoRefresh()` methods in tests and factories.

```php
// ❌ Bad: deprecated Proxy methods
$entity->_real();
$entity->_enableAutoRefresh();
$entity->_disableAutoRefresh();

// ✅ Good: lazy objects handle refresh automatically
$entity = $factory->create(); // auto-refresh works by default
```

### Test Utilities

- **Shared test utilities**: Centralize external resource cleanup/refresh helpers in dedicated traits.

```php
trait ResourceTestTrait
{
    protected function clearSearchIndex(): void { /* ... */ }
    protected function refreshCache(): void { /* ... */ }
}
```

## 🚫 Common Anti-Patterns

### 1. Treating creator metadata as ownership

```php
// ❌ Bad: assumes creator still owns the aggregate
if ($document->getCreatedBy() === $user) {
    // ...
}

// ✅ Good: resolves ownership via dedicated relation
$owner = $ownershipGateway->findOwner($document);

if ($owner?->getUser()?->getId() === $user->getId()) {
    // verified owner
}
```

### 2. Bypassing shared state-transition actions

```php
// ❌ Bad: overwrites status manually and forgets related side effects
$aggregate->setStatus(Status::Draft);

// ✅ Good: reuse centralized action/handler for the status change
$this->handle(new ChangeStatusAction($aggregateId, Status::Draft));
```

### 3. Manual JSON parsing instead of API Platform deserialization

```php
// ❌ Bad: reimplements serializer + validation
$data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
$status = $data['manualStatus'] ?? null;
Assert::oneOf($status, ['accept', 'refuse']);

// ✅ Good: let API Platform hydrate + validate the DTO
#[Post(
    uriTemplate: '/documents/{id}/manual-validate',
    input: ManualValidateDocumentInputDto::class,
)]
```

### 4. N+1 queries in loops

```php
// ❌ Bad: queries inside loop
foreach ($documents as $doc) {
    $actor = $actorGateway->findById($doc->getActorId()); // N+1!
}

// ✅ Good: batch lookup
$actorIds = array_map(fn($d) => $d->getActorId(), $documents);
$actors = $actorGateway->findByIds($actorIds); // 1 query
$actorMap = array_column($actors, null, 'id');
```

### 5. Translation key concatenation at runtime

```php
// ❌ Bad: key built at runtime (missing keys hidden)
$key = "quota.{$type->value}_max";
$message = $translator->trans($key);

// ✅ Good: static key (missing keys caught in review)
$key = $quotaType->getTranslationKey(); // returns 'quota.watchfile_max'
$message = $translator->trans($key);
```

### 6. Direct handler instantiation

```php
// ❌ Bad: bypasses Messenger middleware
$handler = new ValidateDocumentHandler(...);
$result = $handler($action);

// ✅ Good: uses message bus
$result = $this->handle(new ValidateDocumentAction($docId));
```
