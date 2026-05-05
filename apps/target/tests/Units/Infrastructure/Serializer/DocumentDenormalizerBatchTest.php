<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Serializer;

use App\Domain\Actor\Actor;
use App\Domain\Document\Document;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Serializer\DocumentDenormalizer;
use App\Tests\Units\Infrastructure\Doctrine\NullEntityRepository;
use App\Tests\Utils\EntityUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Unit-test the batch-hydration path of {@see DocumentDenormalizer::denormalizeBatch()}:
 * it must collapse the per-document `EntityManager::find()` calls (4 calls per
 * `Document` × N hits = 4N) into one `findBy(['id' => $ids])` per
 * `#[ApiResource]` type (4 total, regardless of N).
 *
 * The single-document `denormalize()` path stays untouched and is covered by
 * the existing integration suite.
 */
#[CoversClass(DocumentDenormalizer::class)]
class DocumentDenormalizerBatchTest extends TestCase
{
    use EntityUtilsTrait;
    private EntityManagerInterface&MockObject $entityManager;

    /** @var PropertyAccessorInterface&Stub */
    private PropertyAccessorInterface $propertyAccessor;
    private DocumentDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Read the id straight off the entity via reflection — `forcePropertyValue`
        // sets it the same way (`Actor::$id` is `private`), and `createStub()`
        // would override `getId()` to return null.
        $this->propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $this->propertyAccessor
            ->method('getValue')
            ->willReturnCallback($this->readPropertyByReflection(...));

        $this->denormalizer = new DocumentDenormalizer($this->entityManager, $this->propertyAccessor);
    }

    public function testEmptyInputReturnsEmptyArrayWithoutAnyDatabaseAccess(): void
    {
        $this->entityManager->expects($this->never())
            ->method('getRepository');

        self::assertSame([], $this->denormalizer->denormalizeBatch([]));
    }

    public function testBatchHydrationIssuesOneFindByPerEntityClass(): void
    {
        // Cover all four `#[ApiResource]` properties on `Document` discovered
        // via reflection by `collectApiResourceIds()`: actor, source,
        // watchFile, updatedBy. Omitting `updatedBy` here would silently let
        // a regression on the User branch slip through.
        $sources = [
            $this->makeSource(
                id: 'doc-1',
                actorId: 'actor-A',
                sourceId: 'src-A',
                watchFileId: 'wf-1',
                updatedById: 'user-A'
            ),
            $this->makeSource(
                id: 'doc-2',
                actorId: 'actor-B',
                sourceId: 'src-A',
                watchFileId: 'wf-1',
                updatedById: 'user-B'
            ),
            $this->makeSource(
                id: 'doc-3',
                actorId: 'actor-A',
                sourceId: 'src-B',
                watchFileId: 'wf-2',
                updatedById: 'user-A'
            ),
        ];

        $actorA = $this->makeEntityStub(Actor::class, 'actor-A');
        $actorB = $this->makeEntityStub(Actor::class, 'actor-B');
        $sourceA = $this->makeEntityStub(Source::class, 'src-A');
        $sourceB = $this->makeEntityStub(Source::class, 'src-B');
        $watchFile1 = $this->makeEntityStub(WatchFile::class, 'wf-1');
        $watchFile2 = $this->makeEntityStub(WatchFile::class, 'wf-2');
        $userA = $this->makeEntityStub(User::class, 'user-A');
        $userB = $this->makeEntityStub(User::class, 'user-B');

        $actorRepo = new NullEntityRepository([$actorA, $actorB]);
        $sourceRepo = new NullEntityRepository([$sourceA, $sourceB]);
        $watchFileRepo = new NullEntityRepository([$watchFile1, $watchFile2]);
        $userRepo = new NullEntityRepository([$userA, $userB]);

        $this->entityManager
            ->expects($this->exactly(4))
            ->method('getRepository')
            ->willReturnMap([
                [Actor::class, $actorRepo],
                [Source::class, $sourceRepo],
                [WatchFile::class, $watchFileRepo],
                [User::class, $userRepo],
            ]);

        $this->entityManager->expects($this->never())
            ->method('find');

        $documents = $this->denormalizer->denormalizeBatch($sources);

        $this->assertSingleFindByWithIds($actorRepo, ['actor-A', 'actor-B']);
        $this->assertSingleFindByWithIds($sourceRepo, ['src-A', 'src-B']);
        $this->assertSingleFindByWithIds($watchFileRepo, ['wf-1', 'wf-2']);
        $this->assertSingleFindByWithIds($userRepo, ['user-A', 'user-B']);

        self::assertCount(3, $documents);
        foreach ($documents as $document) {
            self::assertInstanceOf(Document::class, $document);
        }

        // Pin the assignment itself — `expects()` proves the right lookups
        // were issued, but a regression that silently dropped the preloaded
        // map (e.g. wrong context key) would still pass without these.
        self::assertSame($actorA, $documents[0]->getActor());
        self::assertSame($sourceA, $documents[0]->getSource());
        self::assertSame($watchFile1, $documents[0]->getWatchFile());
        self::assertSame($userA, $documents[0]->getUpdatedBy());

        self::assertSame($actorB, $documents[1]->getActor());
        self::assertSame($sourceA, $documents[1]->getSource());
        self::assertSame($watchFile1, $documents[1]->getWatchFile());
        self::assertSame($userB, $documents[1]->getUpdatedBy());

        self::assertSame($actorA, $documents[2]->getActor());
        self::assertSame($sourceB, $documents[2]->getSource());
        self::assertSame($watchFile2, $documents[2]->getWatchFile());
        self::assertSame($userA, $documents[2]->getUpdatedBy());
    }

    public function testBatchHydrationFallsBackToFindWhenBulkLookupMisses(): void
    {
        // Referenced entity may have been created between the bulk fetch and
        // denormalize — fallback preserves correctness, batch is just an opt.
        $sources = [$this->makeSource(id: 'doc-1', actorId: 'actor-recent')];

        $actorRepo = new NullEntityRepository();
        $this->entityManager
            ->method('getRepository')
            ->willReturn($actorRepo);

        $recentActor = $this->makeEntityStub(Actor::class, 'actor-recent');
        $this->entityManager
            ->expects($this->exactly(1))
            ->method('find')
            ->with(Actor::class, 'actor-recent')
            ->willReturn($recentActor);

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(1, $documents);
        // Pin the hydration: a regression where the fallback `find()` runs
        // but the entity is never assigned would still satisfy `expects()`.
        self::assertSame($recentActor, $documents[0]->getActor());
    }

    public function testPartialBulkHitFallsBackToFindOnlyForMissingIds(): void
    {
        $sources = [
            $this->makeSource(id: 'doc-1', actorId: 'actor-found'),
            $this->makeSource(id: 'doc-2', actorId: 'actor-missing'),
        ];

        $foundActor = $this->makeEntityStub(Actor::class, 'actor-found');
        $missingActor = $this->makeEntityStub(Actor::class, 'actor-missing');

        $actorRepo = new NullEntityRepository([$foundActor]);
        $this->entityManager->method('getRepository')
->willReturn($actorRepo);

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Actor::class, 'actor-missing')
            ->willReturn($missingActor);

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(2, $documents);
        self::assertCount(1, $actorRepo->getFindByCriteriaCalls());
    }

    public function testFindByExceptionFallsBackToFindForAllIdsOfThatClass(): void
    {
        $sources = [
            $this->makeSource(id: 'doc-1', actorId: 'actor-A'),
            $this->makeSource(id: 'doc-2', actorId: 'actor-B'),
        ];

        $actorA = $this->makeEntityStub(Actor::class, 'actor-A');
        $actorB = $this->makeEntityStub(Actor::class, 'actor-B');

        $actorRepo = new NullEntityRepository(
            entities: [],
            findByThrows: new \RuntimeException('DB connection lost'),
        );
        $this->entityManager->method('getRepository')
->willReturn($actorRepo);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(fn (string $class, mixed $id): ?object => match ($id) {
                'actor-A' => $actorA,
                'actor-B' => $actorB,
                default => null,
            });

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(2, $documents);
        // Pin the bulk attempt — a future regression that swallows the call
        // before trying would silently downgrade to per-id find() forever.
        self::assertCount(1, $actorRepo->getFindByCriteriaCalls());
    }

    public function testEntitiesWithNonScalarIdsAreSkippedInPreloadedMap(): void
    {
        $sources = [$this->makeSource(id: 'doc-1', actorId: 'actor-A')];

        // Unset id (`Actor::$id` defaults to `null`) → filtered from `byId`.
        $weirdActor = $this->createStub(Actor::class);

        $actorRepo = new NullEntityRepository([$weirdActor]);
        $this->entityManager->method('getRepository')
->willReturn($actorRepo);

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Actor::class, 'actor-A')
            ->willReturn(null);

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(1, $documents);
    }

    public function testSourcesWithoutAnyApiResourcePropertyIssueNoDatabaseCalls(): void
    {
        $sources = [$this->makeSource(id: 'doc-1'), $this->makeSource(id: 'doc-2')];

        $this->entityManager->expects($this->never())
->method('getRepository');
        $this->entityManager->expects($this->never())
->method('find');

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(2, $documents);
    }

    public function testMalformedActorEntriesAreSkippedSilently(): void
    {
        $sources = [
            [
                'id' => 'doc-1',
                'actor' => 'string-instead-of-array',
            ],
            [
                'id' => 'doc-2',
                'actor' => [
                    'noIdKey' => 'whatever',
                ],
            ],
        ];

        $this->entityManager->expects($this->never())
->method('getRepository');
        $this->entityManager->expects($this->never())
->method('find');

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(2, $documents);
    }

    public function testEmptyStringIdsAreFilteredFromBulkButReachFindFallback(): void
    {
        // `collectApiResourceIds` rejects empty-string ids → no `findBy()`.
        // But `setApiResourceProperty` accepts the empty string (it's still
        // a string) — the asymmetry is intentional: bulk skips known noise,
        // single-path stays permissive. Pin the current behaviour.
        $sources = [[
            'id' => 'doc-1',
            'actor' => [
                'id' => '',
            ],
        ]];

        $this->entityManager->expects($this->never())
->method('getRepository');

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Actor::class, '')
            ->willReturn(null);

        $documents = $this->denormalizer->denormalizeBatch($sources);

        self::assertCount(1, $documents);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function makeEntityStub(string $class, string $id): object
    {
        $stub = $this->createStub($class);
        $this->forcePropertyValue($stub, $id);

        return $stub;
    }

    /**
     * Walk the inheritance chain to find a property — needed because PHPUnit
     * stubs are subclasses and the actual `id` lives on the parent (private).
     */
    private function readPropertyByReflection(object $entity, string $propertyPath): mixed
    {
        $reflection = new \ReflectionClass($entity);
        while (!$reflection->hasProperty($propertyPath)) {
            $parent = $reflection->getParentClass();
            if (false === $parent) {
                return null;
            }
            $reflection = $parent;
        }
        $property = $reflection->getProperty($propertyPath);
        $property->setAccessible(true);

        return $property->isInitialized($entity) ? $property->getValue($entity) : null;
    }

    /**
     * @template T of object
     *
     * @param NullEntityRepository<T> $repo
     * @param list<string>            $expectedIds
     */
    private function assertSingleFindByWithIds(NullEntityRepository $repo, array $expectedIds): void
    {
        $calls = $repo->getFindByCriteriaCalls();
        self::assertCount(1, $calls);
        self::assertEqualsCanonicalizing($expectedIds, $calls[0]['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeSource(
        string $id,
        ?string $actorId = null,
        ?string $sourceId = null,
        ?string $watchFileId = null,
        ?string $updatedById = null,
    ): array {
        $source = [
            'id' => $id,
        ];

        if (null !== $actorId) {
            $source['actor'] = [
                'id' => $actorId,
            ];
        }
        if (null !== $sourceId) {
            $source['source'] = [
                'id' => $sourceId,
            ];
        }
        if (null !== $watchFileId) {
            $source['watchFile'] = [
                'id' => $watchFileId,
            ];
        }
        if (null !== $updatedById) {
            $source['updatedBy'] = [
                'id' => $updatedById,
            ];
        }

        return $source;
    }
}
