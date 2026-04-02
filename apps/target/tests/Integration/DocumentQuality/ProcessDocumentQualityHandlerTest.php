<?php

declare(strict_types=1);

namespace App\Tests\Integration\DocumentQuality;

use App\Application\DocumentQuality\Event\DocumentQualityProcessedEvent;
use App\Application\DocumentQuality\Handler\ProcessDocumentQualityHandler;
use App\Application\DocumentQuality\Message\ProcessDocumentQualityAction;
use App\DataFixtures\Factory\Document\DocumentFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Document\DocumentGatewayInterface;
use App\Tests\Integration\AbstractApiTestCase;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[CoversClass(ProcessDocumentQualityHandler::class)]
class ProcessDocumentQualityHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private Connection $connection;
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->bus = self::getContainer()->get(MessageBusInterface::class);
    }

    public function testQualityReportIsPersistedAfterProcessing(): void
    {
        ['documentId' => $documentId] = $this->dispatchDocument('https://example.com/article');

        $this->transport('quality_processing')
            ->queue()
            ->assertContains(ProcessDocumentQualityAction::class);

        $this->transport('quality_processing')
            ->throwExceptions()
            ->process();

        $this->transport('quality_processing')
            ->queue()
            ->assertEmpty();

        $row = $this->fetchPersistedReport($documentId);

        $this->assertSame($documentId, $row['document_id']);

        /** @var array<string, array{value: float}> $signals */
        $signals = json_decode($row['signals'], true);
        $this->assertArrayHasKey('https', $signals);
    }

    public function testHttpUrlProducesLowHttpsSignal(): void
    {
        ['documentId' => $documentId] = $this->dispatchAndProcess('http://insecure.example.com/article');

        $row = $this->fetchPersistedReport($documentId);

        /** @var array<string, array{value: float}> $signals */
        $signals = json_decode($row['signals'], true);
        $this->assertArrayHasKey('https', $signals);
        $this->assertEquals(0.0, $signals['https']['value']);
    }

    public function testDocumentQualityProcessedEventIsDispatched(): void
    {
        $dispatchedEvent = null;
        self::getContainer()
            ->get('event_dispatcher')
            ->addListener(
                DocumentQualityProcessedEvent::class,
                function (DocumentQualityProcessedEvent $event) use (&$dispatchedEvent): void {
                    $dispatchedEvent = $event;
                }
            );

        ['documentId' => $documentId] = $this->dispatchAndProcess('https://secure.example.com');

        $this->assertNotNull($dispatchedEvent, 'DocumentQualityProcessedEvent should have been dispatched');
        $this->assertSame($documentId, $dispatchedEvent->documentId);
        $this->assertNotEmpty($dispatchedEvent->reportId);
    }

    /**
     * Dispatches a ProcessDocumentQualityAction message without processing the transport.
     * Use this when you need to assert on queue state before/after processing.
     *
     * @return array{documentId: string, watchFileId: string}
     */
    private function dispatchDocument(string $url): array
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $document = DocumentFactory::new()->withWatchFile($watchFile)->with([
            'url' => $url,
        ])->create();
        $documentId = $document->getId();

        $nullGateway = new NullDocumentGateway();
        $nullGateway->save($document);
        self::getContainer()->set(DocumentGatewayInterface::class, $nullGateway);

        $this->bus->dispatch(new ProcessDocumentQualityAction(documentId: $documentId));

        return [
            'documentId' => $documentId,
            'watchFileId' => $watchFile->getId(),
        ];
    }

    /**
     * Dispatches and immediately processes a ProcessDocumentQualityAction message.
     *
     * @return array{documentId: string, watchFileId: string}
     */
    private function dispatchAndProcess(string $url): array
    {
        $result = $this->dispatchDocument($url);

        $this->transport('quality_processing')
            ->throwExceptions()
            ->process();

        return $result;
    }

    /**
     * @return array{id: string, document_id: string, overall_score: string|float, decision: string, decision_reason: string|null, signals: string, computed_at: string}
     */
    private function fetchPersistedReport(string $documentId): array
    {
        /** @var array{id: string, document_id: string, overall_score: string|float, decision: string, decision_reason: string|null, signals: string, computed_at: string}|false $row */
        $row = $this->connection->executeQuery(
            'SELECT * FROM quality_report WHERE document_id = :documentId',
            [
                'documentId' => $documentId,
            ]
        )->fetchAssociative();

        $this->assertNotFalse($row, 'QualityReport should be persisted in database');

        return $row;
    }
}
