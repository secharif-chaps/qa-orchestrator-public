<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Shared;

use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\Shared\EntityEnricherLocatorInterface;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class EntityEnrichmentOrchestratorTest extends TestCase
{
    private EntityEnricherLocatorInterface&Stub $enricherLocator;
    private EntityEnrichmentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->enricherLocator = $this->createStub(EntityEnricherLocatorInterface::class);
        $this->buildOrchestrator();
    }

    private function buildOrchestrator(): void
    {
        $this->orchestrator = new EntityEnrichmentOrchestrator($this->enricherLocator);
    }

    public function testEnrichReturnsEntityWhenNoEnrichersFound(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity = new \stdClass();
        $enricherLocatorMock->expects($this->once())
            ->method('getEnrichersForEntity')
            ->with($entity)
            ->willReturn([])
        ;

        $result = $this->orchestrator->enrich($entity);

        $this->assertSame($entity, $result);
    }

    public function testEnrichAppliesAllEnrichers(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity = new \stdClass();
        $entity->original = true;

        $enricher1 = $this->createMockEnricher();
        $enricher2 = $this->createMockEnricher();

        $enricherLocatorMock->expects($this->once())
            ->method('getEnrichersForEntity')
            ->with($entity)
            ->willReturn([$enricher1, $enricher2])
        ;

        $enrichedEntity1 = clone $entity;
        $enrichedEntity1->enriched1 = true;
        $enricher1->expects($this->once())
            ->method('enrich')
            ->with($entity, [])
            ->willReturn($enrichedEntity1)
        ;

        $enrichedEntity2 = clone $enrichedEntity1;
        $enrichedEntity2->enriched2 = true;
        $enricher2->expects($this->once())
            ->method('enrich')
            ->with($enrichedEntity1, [])
            ->willReturn($enrichedEntity2)
        ;

        $result = $this->orchestrator->enrich($entity);

        $this->assertSame($enrichedEntity2, $result);
    }

    public function testEnrichHandlesEnricherException(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity = new \stdClass();

        $enricher1 = $this->createMockEnricher();
        $enricher2 = $this->createMockEnricher();

        $enricherLocatorMock->expects($this->once())
            ->method('getEnrichersForEntity')
            ->with($entity)
            ->willReturn([$enricher1, $enricher2])
        ;

        $exception = new \Exception('Enricher failed');
        $enricher1->expects($this->once())
            ->method('enrich')
            ->with($entity, [])
            ->willThrowException($exception)
        ;

        $enricher2->expects($this->once())
            ->method('enrich')
            ->with($entity, [])
            ->willReturn($entity)
        ;

        $result = $this->orchestrator->enrich($entity);

        $this->assertSame($entity, $result);
    }

    public function testEnrichPassesContext(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity = new \stdClass();
        $context = [
            'key' => 'value',
        ];

        $enricher = $this->createMockEnricher();

        $enricherLocatorMock->expects($this->once())
            ->method('getEnrichersForEntity')
            ->with($entity)
            ->willReturn([$enricher])
        ;

        $enricher->expects($this->once())
            ->method('enrich')
            ->with($entity, $context)
            ->willReturn($entity)
        ;

        $this->orchestrator->enrich($entity, $context);
    }

    public function testEnrichCollectionReturnsEmptyArrayWhenNoEntities(): void
    {
        $result = $this->orchestrator->enrichCollection([]);

        $this->assertSame([], $result);
    }

    public function testEnrichCollectionGroupsEntitiesByClass(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();
        $entity3 = new TestEntity();

        $enricher = $this->createMockEnricher();

        $enricherLocatorMock->expects($this->exactly(2))
            ->method('getEnrichersFor')
            ->willReturnMap([[\stdClass::class, [$enricher]], [TestEntity::class, []]])
        ;

        $enricher->expects($this->once())
            ->method('enrichCollection')
            ->with([$entity1, $entity2], [])
            ->willReturn([$entity1, $entity2])
        ;

        $result = $this->orchestrator->enrichCollection([$entity1, $entity2, $entity3]);

        $this->assertSame([$entity1, $entity2, $entity3], $result);
    }

    public function testEnrichCollectionHandlesEnricherException(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity = new \stdClass();

        $enricher = $this->createMockEnricher();

        $enricherLocatorMock->expects($this->once())
            ->method('getEnrichersFor')
            ->with(\stdClass::class)
            ->willReturn([$enricher])
        ;

        $exception = new \Exception('Collection enricher failed');
        $enricher->expects($this->once())
            ->method('enrichCollection')
            ->willThrowException($exception)
        ;

        $result = $this->orchestrator->enrichCollection([$entity]);

        $this->assertSame([$entity], $result);
    }

    public function testEnrichCollectionMaintainsOriginalIndexes(): void
    {
        $enricherLocatorMock = $this->createMock(EntityEnricherLocatorInterface::class);
        $this->enricherLocator = $enricherLocatorMock;
        $this->buildOrchestrator();
        $entity1 = new \stdClass();
        $entity1->id = 1;
        $entity1->enriched = null;
        $entity2 = new \stdClass();
        $entity2->id = 2;
        $entity2->enriched = null;

        $enricher = $this->createMockEnricher(
            /**
             * @param iterable<\stdClass> $iterable
             */
            function (iterable $iterable) {
                /** @var \stdClass $entity */
                foreach ($iterable as $entity) {
                    $entity->enriched = true;
                }

                if ($iterable instanceof \Iterator) {
                    $iterable->rewind();
                }

                return $iterable;
            },
        );

        $enricherLocatorMock->expects($this->once())
            ->method('getEnrichersFor')
            ->with(\stdClass::class)
            ->willReturn([$enricher])
        ;

        $result = $this->orchestrator->enrichCollection([
            5 => $entity1,
            10 => $entity2,
        ]);

        $this->assertSame([
            5 => $entity1,
            10 => $entity2,
        ], $result);

        $this->assertTrue($result[5]->enriched);
        $this->assertTrue($result[10]->enriched);
    }

    /**
     * @param callable(iterable<object>): iterable<object>|null $enrichCollection
     *
     * @return EntityEnricherInterface<object>&MockObject
     */
    private function createMockEnricher(?callable $enrichCollection = null): EntityEnricherInterface&MockObject
    {
        $mock = $this->createMock(EntityEnricherInterface::class);

        if (null !== $enrichCollection) {
            $mock->method('enrichCollection')
                ->willReturnCallback($enrichCollection);
        }

        return $mock;
    }
}

class TestEntity
{
}
