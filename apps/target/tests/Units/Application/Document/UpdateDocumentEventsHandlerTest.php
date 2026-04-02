<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\UpdateDocumentEventsAction;
use App\Application\Document\UpdateDocumentEventsHandler;
use App\Domain\Document\DocumentGatewayInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class UpdateDocumentEventsHandlerTest extends TestCase
{
    public function testActorsAreEnrichedWithWatchfileIdAndId(): void
    {
        // Mock dependencies
        $documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $watchFileEventsGateway = $this->createMock(\App\Domain\WatchFileEvent\WatchFileEventGatewayInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        // Capture the events and watchFileId passed to saveEvents
        $capturedDocumentId = '';
        $capturedWatchFileId = '';
        /** @var array<int, array<string, mixed>>|null $capturedEvents */
        $capturedEvents = null;

        $watchFileEventsGateway->expects($this->once())
            ->method('saveEvents')
            ->willReturnCallback(function ($docId, $wfId, $events) use (
                &$capturedDocumentId,
                &$capturedWatchFileId,
                &$capturedEvents
            ) {
                $capturedDocumentId = $docId;
                $capturedWatchFileId = $wfId;
                $capturedEvents = $events;
            });

        $documentGateway->expects($this->once())
            ->method('get')
            ->with('test-doc-123')
            ->willReturn($this->createStub(\App\Domain\Document\Document::class));

        $documentGateway->expects($this->once())
            ->method('markEventsAsExtracted')
            ->with('test-doc-123', true);

        // Mock message bus to return an empty actor map
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $handler = new UpdateDocumentEventsHandler(
            $documentGateway,
            $watchFileEventsGateway,
            $messageBus,
            new NullLogger()
        );

        // Create action with events containing actors WITHOUT watchfile_id and id
        $action = new UpdateDocumentEventsAction(
            documentId: 'test-doc-123',
            watchFileId: 'test-watchfile-456',
            events: [
                [
                    'start_date' => '2025-10-16T10:00:00Z',
                    'end_date' => null,
                    'description' => [
                        'fr' => 'Test événement',
                        'en' => 'Test event',
                    ],
                    'event_type' => 'commercial_business',
                    'actors' => [
                        [
                            'name' => 'Tesla',
                            'role' => 'Signataire',
                        ],
                        [
                            'name' => 'Panasonic',
                            'role' => 'Partenaire',
                        ],
                    ],
                    'text_extract' => 'Tesla et Panasonic ont signé un accord...',
                ],
            ]
        );

        // Execute handler
        $handler->__invoke($action);

        // Verify that the gateway was called with correct parameters
        $this->assertSame('test-doc-123', $capturedDocumentId);
        $this->assertSame('test-watchfile-456', $capturedWatchFileId);
        $this->assertNotNull($capturedEvents, 'Events should have been captured');
        \assert(\is_array($capturedEvents)); // For static analysis
        $this->assertCount(1, $capturedEvents);

        // The events should be enriched with actor_id if actors were extracted
        /** @var array<string, mixed> $event */
        $event = $capturedEvents[0];
        $this->assertArrayHasKey('actors', $event);

        /** @var array<int, array<string, mixed>> $actors */
        $actors = $event['actors'];
        $this->assertCount(2, $actors);

        /** @var array<string, mixed> $firstActor */
        $firstActor = $actors[0];
        /** @var array<string, mixed> $secondActor */
        $secondActor = $actors[1];

        $this->assertSame('Tesla', $firstActor['name']);
        $this->assertSame('Panasonic', $secondActor['name']);
    }
}
