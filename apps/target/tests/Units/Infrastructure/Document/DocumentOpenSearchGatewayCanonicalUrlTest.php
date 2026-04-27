<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Infrastructure\Document\DocumentOpenSearchGateway;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
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
 * Pure unit tests over `findByCanonicalUrl`: validates the OpenSearch query
 * shape (term + optional must_not + size=1) and the error-handling paths
 * without booting the kernel. Round-trip behaviour is covered by the
 * companion integration test.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DocumentOpenSearchGateway::class)]
final class DocumentOpenSearchGatewayCanonicalUrlTest extends TestCase
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
    public function buildsTermQueryOnCanonicalUrlWithSizeOne(): void
    {
        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params): array {
                self::assertSame(Document::INDEX_NAME, $params['index']);
                self::assertSame(1, $params['body']['size']);

                $query = $params['body']['query'];
                self::assertArrayHasKey('term', $query);
                self::assertSame('https://example.com/article', $query['term']['canonicalUrl']['value'] ?? null);

                return [
                    'hits' => [
                        'hits' => [],
                    ],
                ];
            });

        $this->gateway->findByCanonicalUrl('https://example.com/article');
    }

    #[Test]
    public function addsMustNotIdsClauseWhenExcludeDocumentIdProvided(): void
    {
        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params): array {
                $query = $params['body']['query'];
                self::assertArrayHasKey('bool', $query);
                self::assertSame(
                    'https://example.com/article',
                    $query['bool']['must']['term']['canonicalUrl']['value'] ?? null,
                );
                self::assertSame(['exclude-me'], $query['bool']['must_not']['ids']['values'] ?? null);

                return [
                    'hits' => [
                        'hits' => [],
                    ],
                ];
            });

        $this->gateway->findByCanonicalUrl('https://example.com/article', 'exclude-me');
    }

    #[Test]
    public function returnsNullWhenSearchYieldsNoHits(): void
    {
        $this->openSearch
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $result = $this->gateway->findByCanonicalUrl('https://example.com/article');

        self::assertNull($result);
    }

    #[Test]
    public function returnsDenormalizedDocumentOnHit(): void
    {
        $expected = $this->createStub(Document::class);
        $this->denormalizer
            ->method('denormalize')
            ->willReturn($expected);

        $this->openSearch
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'id' => 'doc-1',
                                'canonicalUrl' => 'https://example.com/article',
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this->gateway->findByCanonicalUrl('https://example.com/article');

        self::assertSame($expected, $result);
    }

    #[Test]
    public function returnsNullWhenSourceMissingFromHit(): void
    {
        $this->openSearch
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [[
                        '_id' => 'doc-1',
                    ]],
                ],
            ]);

        $result = $this->gateway->findByCanonicalUrl('https://example.com/article');

        self::assertNull($result);
    }

    #[Test]
    public function returnsNullAndLogsOnMissing404(): void
    {
        $this->openSearch
            ->method('search')
            ->willThrowException(new Missing404Exception('index missing'));

        $result = $this->gateway->findByCanonicalUrl('https://example.com/article');

        self::assertNull($result);
    }

    #[Test]
    public function returnsNullAndLogsOnGenericException(): void
    {
        $this->openSearch
            ->method('search')
            ->willThrowException(new \RuntimeException('upstream timeout'));

        $result = $this->gateway->findByCanonicalUrl('https://example.com/article');

        self::assertNull($result);
    }
}
