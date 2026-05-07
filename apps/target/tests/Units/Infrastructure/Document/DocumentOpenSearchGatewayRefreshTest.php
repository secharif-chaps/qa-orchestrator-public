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
 * Asserts that {@see DocumentOpenSearchGateway::save()} forwards the
 * `refresh=wait_for` option to OpenSearch when the caller flags a sync
 * chain — so a caller that immediately follows up with `findByCollectTaskId`
 * does not hit the refresh interval window.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DocumentOpenSearchGateway::class)]
final class DocumentOpenSearchGatewayRefreshTest extends TestCase
{
    private Client&MockObject $openSearch;
    private NormalizerInterface&Stub $normalizer;
    private DocumentOpenSearchGateway $gateway;

    protected function setUp(): void
    {
        /** @var Client&MockObject $openSearch */
        $openSearch = $this->createMock(Client::class);
        $this->openSearch = $openSearch;
        $this->normalizer = $this->createStub(NormalizerInterface::class);
        $this->normalizer->method('normalize')
->willReturn([
    'id' => 'doc-1',
]);

        $this->gateway = new DocumentOpenSearchGateway(
            $this->openSearch,
            $this->createStub(DenormalizerInterface::class),
            $this->normalizer,
            $this->createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function saveDoesNotForwardRefreshByDefault(): void
    {
        $this->openSearch
            ->expects($this->once())
            ->method('index')
            ->willReturnCallback(function (array $params): array {
                self::assertArrayNotHasKey('refresh', $params);

                return [
                    'result' => 'created',
                ];
            });

        $this->gateway->save($this->makeDocument());
    }

    #[Test]
    public function saveForwardsRefreshWaitForWhenWaitForRefreshIsTrue(): void
    {
        $this->openSearch
            ->expects($this->once())
            ->method('index')
            ->willReturnCallback(function (array $params): array {
                self::assertArrayHasKey('refresh', $params);
                self::assertSame('wait_for', $params['refresh']);

                return [
                    'result' => 'created',
                ];
            });

        $this->gateway->save($this->makeDocument(), waitForRefresh: true);
    }

    private function makeDocument(): Document
    {
        return new Document(
            id: 'doc-1',
            title: 'Test',
            excerpt: 'Test',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test body',
        );
    }
}
