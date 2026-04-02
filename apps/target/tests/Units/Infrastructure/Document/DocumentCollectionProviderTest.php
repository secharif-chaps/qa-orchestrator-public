<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use ApiPlatform\Elasticsearch\Paginator;
use ApiPlatform\Metadata\Operation;
use App\Domain\Document\Document;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\DocumentCollectionProvider;
use App\Infrastructure\OpenSearch\State\CollectionProviderWithAggregations;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[AllowMockObjectsWithoutExpectations]
class DocumentCollectionProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private DocumentCollectionProvider $provider;

    /**
     * @var CollectionProviderWithAggregations<Document>&MockObject
     */
    private CollectionProviderWithAggregations&MockObject $collectionProvider;
    private NullWatchFileGateway $watchFileGateway;
    private Security&MockObject $security;
    private Operation&Stub $operation;

    /** @var PaginatorWithAggregations<Document> */
    private PaginatorWithAggregations $paginatorWithAggregations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collectionProvider = $this->createMock(CollectionProviderWithAggregations::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createMock(Security::class);
        $this->operation = $this->createStub(Operation::class);

        $paginator = new Paginator(
            $this->createStub(DenormalizerInterface::class),
            [
                'hits' => [
                    'hits' => [],
                    'total' => [
                        'value' => 0,
                    ],
                ],
            ],
            Document::class,
            1,
            1,
            []
        );

        /** @var PaginatorWithAggregations<Document> */
        $paginatorWithAggregations = new PaginatorWithAggregations(
            $paginator,
            [
                'actors' => [
                    'buckets' => [],
                ],
                'sources' => [
                    'buckets' => [],
                ],
                'domains' => [
                    'buckets' => [],
                ],
                'statuses' => [
                    'buckets' => [],
                ],
            ],
            Document::class
        );

        $this->paginatorWithAggregations = $paginatorWithAggregations;
        $entityEnrichmentOrchestrator = $this->createStub(EntityEnrichmentOrchestratorInterface::class);
        $entityEnrichmentOrchestrator->method('enrichCollection')
            ->willReturnCallback(function ($entities) {
                return $entities; // Return the same entities without modification
            });

        $this->provider = new DocumentCollectionProvider(
            $this->collectionProvider,
            $this->watchFileGateway,
            $entityEnrichmentOrchestrator,
            $this->security,
        );
    }

    public function testProvideWithValidWatchFileId(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440002';
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'filters' => [
                'existing' => 'filter',
            ],
        ];

        // Mock security check - user has VIEW permission
        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Mock the collection provider to verify it's called with correct parameters
        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                $uriVariables,
                $this->callback(function (array $context) use ($watchFileId) {
                    return isset($context['filters']['watchFile.id'])
                        && $context['filters']['watchFile.id'] === $watchFileId
                        && 'filter' === $context['filters']['existing'];
                })
            )
            ->willReturn($this->paginatorWithAggregations);

        $result = $this->provider->provide($this->operation, $uriVariables, $context);

        $this->assertInstanceOf(PaginatorWithAggregations::class, $result);
    }

    public function testProvideWithWatchFileIdAndNoExistingFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440003';
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = []; // No existing filters

        // Mock security check - user has VIEW permission
        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Mock the collection provider to verify it's called with correct parameters
        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                $uriVariables,
                $this->callback(function (array $context) use ($watchFileId) {
                    return isset($context['filters']['watchFile.id'])
                        && $context['filters']['watchFile.id'] === $watchFileId
                        && \is_array($context['filters']);
                })
            )
            ->willReturn($this->paginatorWithAggregations);

        // Act & Assert - should not throw any exception
        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithNonStringWatchFileId(): void
    {
        $uriVariables = [
            'watchFileId' => 123,
        ]; // Non-string value
        $context = [];

        // Assert will throw InvalidArgumentException for wrong type
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithMissingWatchFileId(): void
    {
        $uriVariables = []; // No watchFileId
        $context = [];

        // Assert will throw InvalidArgumentException for missing key
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithNullWatchFileId(): void
    {
        $uriVariables = [
            'watchFileId' => null,
        ];
        $context = [];

        // Assert - Assert will throw InvalidArgumentException for null value
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithNonExistentWatchFileId(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [];

        $this->expectException(WatchFileNotFoundException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithWatchFileIdAndNonArrayFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440001';
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'filters' => 'not-an-array',
        ]; // Non-array filters

        // Mock security check - user has VIEW permission
        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // should throw InvalidArgumentException when filters is not an array
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithEmptyStringWatchFileId(): void
    {
        $uriVariables = [
            'watchFileId' => '',
        ]; // Empty string
        $context = [];

        // Assert will throw InvalidArgumentException for empty string
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithInvalidUuidFormat(): void
    {
        $uriVariables = [
            'watchFileId' => 'not-a-valid-uuid',
        ];
        $context = [];

        // Assert::uuid will throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithWatchFileIdAndExistingWatchFileIdFilter(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440004';
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'filters' => [
                'existing' => 'filter',
                'watchFile.id' => 'old-value', // Existing watchFile.id filter
            ],
        ];

        // Mock security check - user has VIEW permission
        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Mock the collection provider to verify it's called with correct parameters
        $this->collectionProvider
            ->expects($this->once())
            ->method('provide')
            ->with(
                $this->operation,
                $uriVariables,
                $this->callback(function (array $context) use ($watchFileId) {
                    return isset($context['filters']['watchFile.id'])
                        && $context['filters']['watchFile.id'] === $watchFileId
                        && 'filter' === $context['filters']['existing'];
                })
            )
            ->willReturn($this->paginatorWithAggregations);

        // Act & Assert - should not throw any exception
        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithAccessDenied(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440005';
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [];

        // Mock security check - user does NOT have VIEW permission
        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        // Collection provider should never be called
        $this->collectionProvider
            ->expects($this->never())
            ->method('provide');

        $this->expectException(AccessDeniedException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithWhitespaceOnlyWatchFileId(): void
    {
        $uriVariables = [
            'watchFileId' => '   ',
        ]; // Whitespace only
        $context = [];

        // After trim, becomes empty string, then Assert::uuid fails
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithBooleanWatchFileId(): void
    {
        $uriVariables = [
            'watchFileId' => true,
        ]; // Boolean value
        $context = [];

        // Assert will throw InvalidArgumentException for wrong type
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithArrayWatchFileId(): void
    {
        $uriVariables = [
            'watchFileId' => ['invalid'],
        ]; // Array value
        $context = [];

        // Assert will throw InvalidArgumentException for wrong type
        $this->expectException(\InvalidArgumentException::class);

        $this->provider->provide($this->operation, $uriVariables, $context);
    }
}
