<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileEvent;

use ApiPlatform\Elasticsearch\Paginator;
use ApiPlatform\Metadata\Operation;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\State\CollectionProviderWithAggregations;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Infrastructure\WatchFileEvent\WatchFileEventCollectionProvider;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webmozart\Assert\InvalidArgumentException;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(WatchFileEventCollectionProvider::class)]
final class WatchFileEventCollectionProviderTest extends TestCase
{
    /**
     * @var CollectionProviderWithAggregations<WatchFileEvent>&MockObject
     */
    private CollectionProviderWithAggregations&MockObject $collectionProvider;
    private NullWatchFileGateway $watchFileGateway;
    private Security&MockObject $security;
    private Operation $operation;
    private WatchFileEventCollectionProvider $provider;

    protected function setUp(): void
    {
        /** @var CollectionProviderWithAggregations<WatchFileEvent>&MockObject $collectionProvider */
        $collectionProvider = $this->createMock(CollectionProviderWithAggregations::class);
        $this->collectionProvider = $collectionProvider;
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createMock(Security::class);
        $this->operation = $this->createStub(Operation::class);

        $this->provider = new WatchFileEventCollectionProvider(
            $this->collectionProvider,
            $this->watchFileGateway,
            $this->security,
        );
    }

    #[Test]
    public function itThrowsExceptionWhenWatchFileIdIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Watch file ID is required');

        $this->provider->provide($this->operation, [], []);
    }

    #[Test]
    public function itThrowsExceptionWhenWatchFileIdIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Watch file ID must be a non-empty string');

        $this->provider->provide($this->operation, [
            'watchFileId' => '',
        ], []);
    }

    #[Test]
    public function itThrowsExceptionWhenWatchFileIdIsNotValidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Watch file ID must be a valid UUID');

        $this->provider->provide($this->operation, [
            'watchFileId' => 'invalid-uuid',
        ], []);
    }

    #[Test]
    public function itThrowsAccessDeniedExceptionWhenUserDoesNotHavePermission(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to view events for this watch file');

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);
    }

    #[Test]
    public function itProvidesCollectionWhenUserHasPermission(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $expectedResult = $this->createPaginator(3);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                [
                    'watchFileId' => $watchFileId,
                ],
                $this->callback(function (array $context) use ($watchFileId) {
                    return isset($context['filters'])
                        && \is_array($context['filters'])
                        && $context['filters']['watchFile.id'] === $watchFileId;
                })
            )
            ->willReturn($expectedResult);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function itAddsWatchFileIdFilterToExistingFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $existingFilters = [
            'startDate' => '2025-10-01',
            'endDate' => '2025-10-31',
        ];
        $context = [
            'filters' => $existingFilters,
        ];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                [
                    'watchFileId' => $watchFileId,
                ],
                $this->callback(function (array $context) use ($watchFileId, $existingFilters) {
                    return isset($context['filters'])
                        && $context['filters']['watchFile.id'] === $watchFileId
                        && $context['filters']['startDate'] === $existingFilters['startDate']
                        && $context['filters']['endDate'] === $existingFilters['endDate'];
                })
            )
            ->willReturn($this->createPaginator());

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);
    }

    #[Test]
    public function itCreatesFiltersArrayWhenNotPresent(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $context = [];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                [
                    'watchFileId' => $watchFileId,
                ],
                $this->callback(function (array $context) use ($watchFileId) {
                    return isset($context['filters'])
                        && \is_array($context['filters'])
                        && $context['filters']['watchFile.id'] === $watchFileId
                        && 1 === \count($context['filters']);
                })
            )
            ->willReturn($this->createPaginator());

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);
    }

    #[Test]
    public function itTrimsWatchFileId(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                [
                    'watchFileId' => '  ' . $watchFileId . '  ',
                ],
                $this->callback(function (array $context) use ($watchFileId) {
                    // The trimmed ID should be used in the filter
                    return $context['filters']['watchFile.id'] === $watchFileId;
                })
            )
            ->willReturn($this->createPaginator());

        $this->provider->provide($this->operation, [
            'watchFileId' => '  ' . $watchFileId . '  ',
        ], []);
    }

    #[Test]
    public function itPassesThroughUriVariablesToCollectionProvider(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $uriVariables = [
            'watchFileId' => $watchFileId,
            'additionalParam' => 'value',
        ];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $this->anything())
            ->willReturn($this->createPaginator());

        $this->provider->provide($this->operation, $uriVariables, []);
    }

    #[Test]
    public function itPreservesOtherContextData(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $context = [
            'filters' => [
                'existingFilter' => 'value',
            ],
            'groups' => ['read'],
            'operation_name' => 'get_collection',
        ];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                [
                    'watchFileId' => $watchFileId,
                ],
                $this->callback(function (array $actualContext) use ($watchFileId) {
                    return isset($actualContext['filters'])
                        && $actualContext['filters']['watchFile.id'] === $watchFileId
                        && 'value' === $actualContext['filters']['existingFilter']
                        && $actualContext['groups'] === ['read']
                        && 'get_collection' === $actualContext['operation_name'];
                })
            )
            ->willReturn($this->createPaginator());

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);
    }

    #[Test]
    public function itThrowsExceptionWhenFiltersIsNotArray(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $context = [
            'filters' => 'invalid',
        ];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Context filters must be an array');

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);
    }

    #[Test]
    public function itReturnsEmptyPaginatorWhenNoResults(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $emptyPaginator = $this->createPaginator(0);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn($emptyPaginator);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        self::assertSame($emptyPaginator, $result);
        self::assertSame(0, $result->count());
    }

    #[Test]
    public function itReturnsPaginatorWithResults(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $paginator = $this->createPaginator(5);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn($paginator);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        self::assertSame($paginator, $result);
        self::assertSame(5, $result->count());
    }

    private function createWatchFile(string $watchFileId): WatchFile
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));

        // Use reflection to set the ID
        $reflection = new \ReflectionClass($watchFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($watchFile, $watchFileId);

        $this->watchFileGateway->save($watchFile);

        return $watchFile;
    }

    /**
     * Creates a PaginatorWithAggregations instance for testing.
     *
     * @return PaginatorWithAggregations<WatchFileEvent>
     */
    private function createPaginator(int $totalHits = 0): PaginatorWithAggregations
    {
        $hits = [];
        for ($i = 0; $i < $totalHits; ++$i) {
            $hits[] = [
                '_id' => 'event-' . $i,
                '_source' => [],
            ];
        }

        $paginator = new Paginator(
            $this->createStub(DenormalizerInterface::class),
            [
                'hits' => [
                    'hits' => $hits,
                    'total' => [
                        'value' => $totalHits,
                    ],
                ],
            ],
            WatchFileEvent::class,
            10,
            0,
            []
        );

        /** @var PaginatorWithAggregations<WatchFileEvent> */
        return new PaginatorWithAggregations($paginator, [], WatchFileEvent::class);
    }
}
