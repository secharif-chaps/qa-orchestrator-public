<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\User\User;
use App\Infrastructure\Document\DocumentSeenStatusEnricher;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentSeenStatusEnricher::class)]
class DocumentSeenStatusEnricherTest extends TestCase
{
    use MockHelpersTrait;
    private DocumentSeenStatusGatewayInterface&Stub $gateway;
    private DocumentSeenStatusEnricher $enricher;

    protected function setUp(): void
    {
        $this->gateway = $this->createStub(DocumentSeenStatusGatewayInterface::class);
        $this->buildEnricher();
    }

    private function buildEnricher(): void
    {
        $this->enricher = new DocumentSeenStatusEnricher($this->gateway);
    }

    public function testEnrichSetsIsSeenToTrueWhenDocumentIsSeen(): void
    {
        $gateway = $this->createMockWithExpectations(DocumentSeenStatusGatewayInterface::class);
        $this->gateway = $gateway;
        $this->buildEnricher();

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $document = new Document(
            id: 'test-doc-1',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
            status: DocumentStatus::PENDING
        );

        $gateway
            ->expects($this->once())
            ->method('isDocumentSeen')
            ->with($user, 'test-doc-1')
            ->willReturn(true);

        $result = $this->enricher->enrich($document, [
            'user' => $user,
        ]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertTrue($result->getIsSeen());
    }

    public function testEnrichSetsIsSeenToFalseWhenDocumentIsNotSeen(): void
    {
        $gateway = $this->createMockWithExpectations(DocumentSeenStatusGatewayInterface::class);
        $this->gateway = $gateway;
        $this->buildEnricher();

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $document = new Document(
            id: 'test-doc-2',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
            status: DocumentStatus::PENDING
        );

        $gateway
            ->expects($this->once())
            ->method('isDocumentSeen')
            ->with($user, 'test-doc-2')
            ->willReturn(false);

        $result = $this->enricher->enrich($document, [
            'user' => $user,
        ]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertFalse($result->getIsSeen());
    }

    public function testEnrichReturnsOriginalEntityWhenNoUserInContext(): void
    {
        $gateway = $this->createMockWithExpectations(DocumentSeenStatusGatewayInterface::class);
        $this->gateway = $gateway;
        $this->buildEnricher();

        $document = new Document(
            id: 'test-doc-3',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
            status: DocumentStatus::PENDING
        );

        $gateway
            ->expects($this->never())
            ->method('isDocumentSeen');

        $result = $this->enricher->enrich($document, []);

        $this->assertSame($document, $result);
        $this->assertFalse($result->getIsSeen());
    }

    public function testEnrichCollectionUsesBatchQuery(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');
        $document1 = new Document(
            id: 'test-doc-1',
            title: 'Test Document 1',
            excerpt: 'Test excerpt 1',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content 1',
            status: DocumentStatus::PENDING
        );
        $document2 = new Document(
            id: 'test-doc-2',
            title: 'Test Document 2',
            excerpt: 'Test excerpt 2',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content 2',
            status: DocumentStatus::PENDING
        );

        $documents = [$document1, $document2];

        $gateway = $this->createMockWithExpectations(DocumentSeenStatusGatewayInterface::class);
        $this->gateway = $gateway;
        $this->buildEnricher();

        $gateway
            ->expects($this->once())
            ->method('areDocumentsSeen')
            ->with($user, ['test-doc-1', 'test-doc-2'])
            ->willReturn([
                'test-doc-1' => true,
                'test-doc-2' => false,
            ]);

        $result = $this->enricher->enrichCollection($documents, [
            'user' => $user,
        ]);

        $this->assertTrue($document1->getIsSeen());
        $this->assertFalse($document2->getIsSeen());
        $this->assertSame($documents, $result);
    }

    public function testEnrichCollectionReturnsEarlyWhenNoUser(): void
    {
        $gateway = $this->createMockWithExpectations(DocumentSeenStatusGatewayInterface::class);
        $this->gateway = $gateway;
        $this->buildEnricher();

        $document = new Document(
            id: 'test-doc-1',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
            status: DocumentStatus::PENDING
        );

        $gateway
            ->expects($this->never())
            ->method('isDocumentSeen');

        $result = $this->enricher->enrichCollection([$document], []);

        $this->assertFalse($document->getIsSeen());
    }

    public function testSupportsReturnsDocumentClass(): void
    {
        $this->assertEquals(Document::class, $this->enricher->supports());
    }
}
