<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Handler;

use App\Application\Document\AddDocumentAction;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Infrastructure\Collect\Bakus\BakusProviderGateway;
use App\Infrastructure\Collect\Bakus\Handler\BakusMergedResultHandler;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BakusMergedResultHandlerTest extends TestCase
{
    private BakusMergedResultHandler $handler;
    private NullMessageBus $messageBus;
    private BakusProviderGateway&Stub $gateway;
    private ValidatorInterface&Stub $validator;

    protected function setUp(): void
    {
        $this->messageBus = new NullMessageBus();
        $this->gateway = $this->createStub(BakusProviderGateway::class);
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList());
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new BakusMergedResultHandler($this->messageBus, $this->gateway, $this->validator, null);
    }

    public function testSupportsWithMergedResultType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'data' => 'test',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertTrue($this->handler->supports($event));
    }

    public function testSupportsWithRawResultType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'raw_result',
                'data' => 'test',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertTrue($this->handler->supports($event));
    }

    public function testSupportsWithDocumentRefinedResultType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'document_refined_result',
                'data' => 'test',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertTrue($this->handler->supports($event));
    }

    public function testSupportsWithDifferentType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'other_type',
                'data' => 'test',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertFalse($this->handler->supports($event));
    }

    public function testSupportsWithMissingType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'data' => 'test',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertFalse($this->handler->supports($event));
    }

    public function testInvokeWithValidMergedResultData(): void
    {
        $this->gateway->method('getDocumentContent')
            ->willReturn('PDF document content here...');

        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                    'url_title' => 'Test Document',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                    'ts_collection' => '2024-01-15T10:30:00Z',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(1, $this->messageBus->getDispatchedMessages());
        $action = $this->messageBus->getDispatchedMessages()[0];
        $this->assertInstanceOf(AddDocumentAction::class, $action);
        $this->assertSame('collect-task-123', $action->collectTaskId);
    }

    public function testInvokeWithInvalidResultDataStructure(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => 'invalid_string_instead_of_array',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithMissingRawId(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithMissingHashDocumentSha1(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'url' => 'https://example.com/document.pdf',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithEmptyHashDocumentSha1(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'hash_document_sha1' => '',
                    'url' => 'https://example.com/document.pdf',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithNonStringHashDocumentSha1(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'hash_document_sha1' => 12345,
                    'url' => 'https://example.com/document.pdf',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithGatewayException(): void
    {
        $this->gateway->method('getDocumentContent')
            ->willThrowException(new \RuntimeException('Gateway connection failed'));

        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-456',
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithInvalidDocumentValidation(): void
    {
        $this->gateway->method('getDocumentContent')
            ->willReturn('Document content here...');

        $violation = new ConstraintViolation('This value is not a valid URL.', '', [], '', 'url', 'invalid-url');
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));
        $this->validator = $validator;
        $this->buildHandler();

        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-789',
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'invalid-url',
                    'url_title' => 'Test Document',
                    'content_type' => 'text/html',
                    'ts_collection' => '2024-01-15T10:30:00Z',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithInvalidDocumentOrigin(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                ],
                'document_origin' => 'processed',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithInvalidContentTypeType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                    'content_type' => 12345,
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithUnsupportedContentType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'raw_id' => 'raw-id-123',
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                    'content_type' => 'application/pdf',
                ],
                'document_origin' => 'raw',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testBuildDocumentWithValidData(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document.pdf',
                    'url_title' => 'example.com',
                    'fqdn' => 'example.com',
                    'content_type' => 'application/pdf',
                    'ts_collection' => '2024-01-15T10:30:00Z',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument(
            'PDF document content here...',
            $event,
            'raw-id-123',
            'merged_result'
        );

        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('example.com', $document->getTitle());
        $this->assertEquals('PDF document content here...', $document->getExcerpt());
        $this->assertEquals('pdf', $document->getType());
        $this->assertEquals('PDF document content here...', $document->getContent());
        $this->assertEquals(DocumentStatus::PENDING, $document->getStatus());
        $this->assertEquals('en', $document->getLanguage());
        $this->assertFalse($document->isInteresting());
        $this->assertFalse($document->isCfcRestricted());
        $this->assertNull($document->getInsight());
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDateCollect());
    }

    public function testBuildDocumentWithHtmlContentType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/page.html',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                    'ts_collection' => '2024-01-15T10:30:00Z',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $content = '<html><body><h1>Test Document</h1><p>This is a test HTML document.</p></body></html>';
        $document = $this->handler->buildDocument($content, $event, 'raw-id-456', 'merged_result');

        $this->assertEquals('html', $document->getType());
        $this->assertEquals('Test DocumentThis is a test HTML document.', $document->getExcerpt());
    }

    public function testBuildDocumentWithTitleFromUrl(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://subdomain.example.com/specific-page',
                    'url_title' => 'subdomain.example.com',
                    'fqdn' => '',
                    'content_type' => 'text/html',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('HTML content', $event, 'raw-id-789', 'merged_result');

        $this->assertEquals('subdomain.example.com', $document->getTitle());
    }

    public function testBuildDocumentFallsBackToUrlPathWhenNoTitle(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://frontlinedefenders.org/en/human-rights-defenders',
                    'fqdn' => '',
                    'content_type' => 'text/html',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('HTML content', $event, 'raw-id-101', 'merged_result');

        $this->assertEquals('Human rights defenders', $document->getTitle());
    }

    public function testBuildDocumentFallsBackToHostWhenUrlHasNoPath(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://frontlinedefenders.org/',
                    'fqdn' => '',
                    'content_type' => 'text/html',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('HTML content', $event, 'raw-id-102', 'merged_result');

        $this->assertEquals('frontlinedefenders.org', $document->getTitle());
    }

    public function testBuildDocumentWithUntitledFallbackWhenNoUrlAvailable(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'content_type' => 'text/html',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('HTML content', $event, 'raw-id-103', 'merged_result');

        $this->assertEquals('Untitled Document', $document->getTitle());
    }

    public function testBuildDocumentWithLongContent(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/long-document',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $longContent = str_repeat('This is a very long document content that should be truncated in the excerpt. ', 10);
        $document = $this->handler->buildDocument($longContent, $event, 'raw-id-202', 'merged_result');

        $this->assertLessThanOrEqual(200, mb_strlen($document->getExcerpt()));
        $this->assertStringStartsWith('This is a very long document content', $document->getExcerpt());
    }

    public function testBuildDocumentWithValidTimestamp(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                    'ts_collection' => '2024-01-15T10:30:00Z',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('Content', $event, 'raw-id-303', 'merged_result');

        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDateCollect());
    }

    public function testBuildDocumentWithEmptyTimestamp(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                    'ts_collection' => '',
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('Content', $event, 'raw-id-404', 'merged_result');

        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDateCollect());
    }

    public function testBuildDocumentWithNonStringTimestamp(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => [
                    'hash_document_sha1' => 'abc123def456',
                    'url' => 'https://example.com/document',
                    'fqdn' => 'example.com',
                    'content_type' => 'text/html',
                    'ts_collection' => 1234567890,
                ],
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('Content', $event, 'raw-id-505', 'merged_result');

        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDateCollect());
    }

    public function testBuildDocumentWithNonArrayResultData(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'merged_result',
                'result' => 'invalid_string_instead_of_array',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $document = $this->handler->buildDocument('Content', $event, 'raw-id-606', 'merged_result');

        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('Untitled Document', $document->getTitle());
        $this->assertEquals('html', $document->getType());
    }
}
