<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Infrastructure\Document\DocumentOpenSearchGateway;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Pure unit tests over `findByCollectTaskId`: validates the OpenSearch query
 * shape (constant_score + term filter on `collectTaskId`, size>1) and that
 * empty hits degrade to an empty list rather than throwing. Powers the
 * provider-agnostic CLI sync recap that needs to retrieve all Documents
 * produced by a CollectTask after dispatch.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DocumentOpenSearchGateway::class)]
final class DocumentOpenSearchGatewayCollectTaskIdTest extends TestCase
{
    private Client&MockObject $openSearch;
    private DenormalizerInterface&Stub $denormalizer;
    private NormalizerInterface&Stub $normalizer;
    private LoggerInterface&Stub $logger;
    private DocumentOpenSearchGateway $gateway;

    protected function setUp(): void
    {
        /** @var Client&MockObject $openSearch */
        $openSearch = $this->createMock(Client::class);
        $this->openSearch = $openSearch;
        $this->denormalizer = $this->createStub(DenormalizerInterface::class);
        $this->normalizer = $this->createStub(NormalizerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->gateway = new DocumentOpenSearchGateway(
            $this->openSearch,
            $this->denormalizer,
            $this->normalizer,
            $this->logger,
        );
    }

    #[Test]
    public function buildsConstantScoreTermQueryOnCollectTaskId(): void
    {
        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params): array {
                self::assertSame(Document::INDEX_NAME, $params['index']);
                self::assertGreaterThan(1, $params['body']['size']);

                $query = $params['body']['query'];
                self::assertArrayHasKey('constant_score', $query);
                self::assertSame(
                    'task-abc-123',
                    $query['constant_score']['filter']['term']['collectTaskId']['value'] ?? null,
                );

                return [
                    'hits' => [
                        'hits' => [],
                    ],
                ];
            });

        $this->gateway->findByCollectTaskId('task-abc-123');
    }

    #[Test]
    public function returnsEmptyArrayWhenNoHits(): void
    {
        $this->openSearch
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $result = $this->gateway->findByCollectTaskId('task-no-match');

        self::assertSame([], $result);
    }

    #[Test]
    public function denormalizesEveryHitWhenMultipleDocumentsMatch(): void
    {
        $this->openSearch
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'id' => 'doc-1',
                                'title' => 'Doc 1',
                            ],
                        ],
                        [
                            '_source' => [
                                'id' => 'doc-2',
                                'title' => 'Doc 2',
                            ],
                        ],
                    ],
                ],
            ]);

        $doc1 = new Document(
            'doc-1',
            'Doc 1',
            'excerpt',
            'html',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'content',
        );
        $doc2 = new Document(
            'doc-2',
            'Doc 2',
            'excerpt',
            'html',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'content',
        );

        // The gateway falls back to per-hit denormalize when the denormalizer
        // does not implement the batch hydration trait.
        $this->denormalizer
            ->method('denormalize')
            ->willReturnOnConsecutiveCalls($doc1, $doc2);

        $result = $this->gateway->findByCollectTaskId('task-with-2-docs');

        self::assertCount(2, $result);
        self::assertSame('doc-1', $result[0]->getId());
        self::assertSame('doc-2', $result[1]->getId());
    }
}
