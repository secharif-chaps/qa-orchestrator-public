<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\BatchManualValidateDocumentAction;
use App\Application\Document\BatchManualValidateDocumentHandler;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\Document\SummaryStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Document\NullDocumentValidationGateway;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\UserTestHelperTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

#[CoversClass(BatchManualValidateDocumentHandler::class)]
class BatchManualValidateDocumentHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use UserTestHelperTrait;
    private BatchManualValidateDocumentHandler $handler;
    private NullDocumentGateway $documentGateway;
    private NullDocumentValidationGateway $documentValidationGateway;
    private NullUserGateway $userGateway;
    private Security&Stub $security;
    private EventDispatcherInterface&Stub $eventDispatcher;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $this->documentValidationGateway = new NullDocumentValidationGateway();
        $this->userGateway = new NullUserGateway();
        $this->security = $this->createStub(Security::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new BatchManualValidateDocumentHandler(
            $this->documentGateway,
            $this->documentValidationGateway,
            $this->userGateway,
            $this->security,
            $this->eventDispatcher,
            new NullLogger(),
        );
    }

    public function testHandleBatchAcceptAllDocumentsSuccess(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $this->security->method('isGrantedForUser')
->willReturn(true);

        $eventDispatcherMock->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user = new User('550e8400-e29b-41d4-a716-446655440001', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = [
            '550e8400-e29b-41d4-a716-446655440010',
            '550e8400-e29b-41d4-a716-446655440011',
            '550e8400-e29b-41d4-a716-446655440012',
        ];

        foreach ($docIds as $docId) {
            $document = new Document(
                $docId,
                'Test Document',
                'Test excerpt',
                'pdf',
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                'Test content',
                DocumentStatus::PENDING,
            );
            $document->setWatchFile($watchFile);
            $this->documentGateway->save($document);
        }

        $this->userGateway->save($user);

        $action = new BatchManualValidateDocumentAction(
            documentIds: $docIds,
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(3, $result->validated_count);
        $this->assertEquals(0, $result->failed_count);
        $this->assertCount(3, $result->results);

        foreach ($result->results as $r) {
            $this->assertEquals('success', $r['status']);
            $this->assertArrayNotHasKey('reason', $r);
        }

        $this->assertEquals(3, $this->documentValidationGateway->count());
    }

    public function testHandleBatchRefuseDocuments(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $this->security->method('isGrantedForUser')
->willReturn(true);

        $eventDispatcherMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user = new User('550e8400-e29b-41d4-a716-446655440002', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = ['550e8400-e29b-41d4-a716-446655440020', '550e8400-e29b-41d4-a716-446655440021'];

        foreach ($docIds as $docId) {
            $document = new Document(
                $docId,
                'Test Document',
                'Test excerpt',
                'pdf',
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                'Test content',
                DocumentStatus::PENDING,
            );
            $document->setWatchFile($watchFile);
            $this->documentGateway->save($document);
        }

        $this->userGateway->save($user);

        $action = new BatchManualValidateDocumentAction(
            documentIds: $docIds,
            action: ManualValidationStatus::REFUSED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(2, $result->validated_count);
        $this->assertEquals(0, $result->failed_count);
    }

    public function testHandleBatchDoesNotTriggerSummaryIfAlreadyCompleted(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $this->security->method('isGrantedForUser')
->willReturn(true);

        $eventDispatcherMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user = new User('550e8400-e29b-41d4-a716-446655440060', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = ['550e8400-e29b-41d4-a716-446655440061', '550e8400-e29b-41d4-a716-446655440062'];

        $doc1 = new Document(
            $docIds[0],
            'Document 1',
            'Excerpt 1',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Content 1',
            DocumentStatus::PENDING,
        );
        $doc1->setWatchFile($watchFile);
        $doc1->setSummaryStatus(SummaryStatus::COMPLETED);
        $this->documentGateway->save($doc1);

        $doc2 = new Document(
            $docIds[1],
            'Document 2',
            'Excerpt 2',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Content 2',
            DocumentStatus::PENDING,
        );
        $doc2->setWatchFile($watchFile);
        $doc2->setSummaryStatus(SummaryStatus::PENDING);
        $this->documentGateway->save($doc2);

        $this->userGateway->save($user);

        $action = new BatchManualValidateDocumentAction(
            documentIds: $docIds,
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(2, $result->validated_count);
        $this->assertEquals(0, $result->failed_count);
    }

    public function testHandleBatchWithEmptyArray(): void
    {
        $this->security->method('isGrantedForUser')
->willReturn(true);

        $user = new User('550e8400-e29b-41d4-a716-446655440070', 'test@example.com', ['ROLE_USER']);

        $this->userGateway->save($user);

        $action = new BatchManualValidateDocumentAction(
            documentIds: [],
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(0, $result->validated_count);
        $this->assertEquals(0, $result->failed_count);
        $this->assertCount(0, $result->results);
    }

    public function testHandleBatchWithDocumentWithoutWatchFile(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildHandler();
        $user = new User('550e8400-e29b-41d4-a716-446655440090', 'test@example.com', ['ROLE_USER']);
        $this->userGateway->save($user);

        $docId = '550e8400-e29b-41d4-a716-446655440091';
        $document = new Document(
            $docId,
            'Test Document',
            'Test excerpt',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $this->documentGateway->save($document);

        $securityMock->expects($this->once())
            ->method('isGrantedForUser')
            ->willReturn(false);

        $action = new BatchManualValidateDocumentAction(
            documentIds: [$docId],
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(0, $result->validated_count);
        $this->assertEquals(1, $result->failed_count);

        $firstResult = $result->results[0];
        $this->assertEquals('error', $firstResult['status']);
        $this->assertArrayHasKey('reason', $firstResult);
        $this->assertIsString($firstResult['reason'] ?? null);
        $this->assertEquals('Insufficient permissions', $firstResult['reason']);
    }

    public function testHandleBatchWithWatchFileUserNotFound(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildHandler();
        $user = new User('550e8400-e29b-41d4-a716-446655440110', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docId = '550e8400-e29b-41d4-a716-446655440111';
        $document = new Document(
            $docId,
            'Test Document',
            'Test excerpt',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $this->documentGateway->save($document);

        $this->userGateway->save($user);

        $securityMock->expects($this->once())
            ->method('isGrantedForUser')
            ->willReturn(false);

        $action = new BatchManualValidateDocumentAction(
            documentIds: [$docId],
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(0, $result->validated_count);
        $this->assertEquals(1, $result->failed_count);

        $firstResult = $result->results[0];
        $this->assertEquals('error', $firstResult['status']);
        $this->assertArrayHasKey('reason', $firstResult);
        $this->assertIsString($firstResult['reason'] ?? null);
        $this->assertEquals('Insufficient permissions', $firstResult['reason']);
    }

    public function testHandleBatchWithMaximumAllowedDocuments(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $this->security->method('isGrantedForUser')
->willReturn(true);

        $eventDispatcherMock->expects($this->exactly(50))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user = new User('550e8400-e29b-41d4-a716-446655440120', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = [];
        for ($i = 0; $i < 50; ++$i) {
            $docId = '550e8400-e29b-41d4-a716-44665544' . str_pad((string) $i, 4, '0', \STR_PAD_LEFT);
            $docIds[] = $docId;

            $document = new Document(
                $docId,
                "Test Document $i",
                "Test excerpt $i",
                'pdf',
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                "Test content $i",
                DocumentStatus::PENDING,
            );
            $document->setWatchFile($watchFile);
            $this->documentGateway->save($document);
        }

        $this->userGateway->save($user);

        $action = new BatchManualValidateDocumentAction(
            documentIds: $docIds,
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(50, $result->validated_count);
        $this->assertEquals(0, $result->failed_count);
        $this->assertCount(50, $result->results);

        $this->assertEquals(50, $this->documentValidationGateway->count());
    }

    public function testHandleBatchWithMixedValidationStatuses(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $this->security->method('isGrantedForUser')
->willReturn(true);

        $eventDispatcherMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user = new User('550e8400-e29b-41d4-a716-446655440130', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = ['550e8400-e29b-41d4-a716-446655440131', '550e8400-e29b-41d4-a716-446655440132'];

        foreach ($docIds as $docId) {
            $document = new Document(
                $docId,
                'Test Document',
                'Test excerpt',
                'pdf',
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                'Test content',
                DocumentStatus::PENDING,
            );
            $document->setWatchFile($watchFile);
            $this->documentGateway->save($document);
        }

        $this->userGateway->save($user);

        $acceptAction = new BatchManualValidateDocumentAction(
            documentIds: [$docIds[0]],
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $acceptResult = ($this->handler)($acceptAction);

        $this->assertEquals(1, $acceptResult->validated_count);
        $this->assertEquals(0, $acceptResult->failed_count);

        $refuseAction = new BatchManualValidateDocumentAction(
            documentIds: [$docIds[1]],
            action: ManualValidationStatus::REFUSED,
            validatedByUserId: $this->getUserId($user),
        );

        $refuseResult = ($this->handler)($refuseAction);

        $this->assertEquals(1, $refuseResult->validated_count);
        $this->assertEquals(0, $refuseResult->failed_count);

        $this->assertEquals(2, $this->documentValidationGateway->count());

        $validations = $this->documentValidationGateway->getAll();
        $this->assertEquals(ManualValidationStatus::ACCEPTED, $validations[0]->getAction());
        $this->assertEquals(ManualValidationStatus::REFUSED, $validations[1]->getAction());
    }
}
