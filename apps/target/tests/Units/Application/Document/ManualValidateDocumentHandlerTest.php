<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\ManualValidateDocumentAction;
use App\Application\Document\ManualValidateDocumentHandler;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Document\NullDocumentValidationGateway;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Utils\UserTestHelperTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(ManualValidateDocumentHandler::class)]
class ManualValidateDocumentHandlerTest extends TestCase
{
    use UserTestHelperTrait;
    private ManualValidateDocumentHandler $handler;
    private NullDocumentGateway $documentGateway;
    private NullDocumentValidationGateway $documentValidationGateway;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private NullUserGateway $userGateway;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $this->documentValidationGateway = new NullDocumentValidationGateway();
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->userGateway = new NullUserGateway();

        $this->handler = new ManualValidateDocumentHandler(
            $this->documentGateway,
            $this->documentValidationGateway,
            $this->userGateway,
            $this->eventDispatcher,
            new NullLogger(),
        );
    }

    public function testHandleAcceptDocument(): void
    {
        $documentId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User('550e8400-e29b-41d4-a716-446655440001', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            $documentId,
            'Test Document',
            'Test excerpt for document',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);

        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: '550e8400-e29b-41d4-a716-446655440000',
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);

        $this->assertEquals(1, $this->documentValidationGateway->count());
        $validations = $this->documentValidationGateway->getAll();
        $this->assertEquals($documentId, $validations[0]->getDocumentId()->toString());
        $this->assertEquals($this->getUserId($user), $validations[0]->getUser()->getId());
        $this->assertEquals(ManualValidationStatus::ACCEPTED, $validations[0]->getAction());
    }

    public function testHandleRefuseDocument(): void
    {
        $user = new User('550e8400-e29b-41d4-a716-446655440002', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            '550e8400-e29b-41d4-a716-446655440000',
            'Test Document',
            'Test excerpt for document',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);

        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: '550e8400-e29b-41d4-a716-446655440000',
            action: ManualValidationStatus::REFUSED,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);

        $this->assertEquals(1, $this->documentValidationGateway->count());
    }

    public function testHandleChangeValidationStatus(): void
    {
        $user = new User('550e8400-e29b-41d4-a716-446655440004', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            '550e8400-e29b-41d4-a716-446655440000',
            'Test Document',
            'Test excerpt for document',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $document->manuallyValidate(ManualValidationStatus::ACCEPTED, $user);

        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: '550e8400-e29b-41d4-a716-446655440000',
            action: ManualValidationStatus::REFUSED,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);

        $this->assertEquals(1, $this->documentValidationGateway->count());
    }

    public function testHandleDoesNotTriggerSummaryIfAlreadyProcessed(): void
    {
        $user = new User('550e8400-e29b-41d4-a716-446655440005', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            '550e8400-e29b-41d4-a716-446655440000',
            'Test Document',
            'Test excerpt for document',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);

        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: '550e8400-e29b-41d4-a716-446655440000',
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);
    }

    public function testHandleResetValidationFromRefusedToUncertain(): void
    {
        $documentId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User('550e8400-e29b-41d4-a716-446655440006', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            $documentId,
            'Test Document',
            'Test excerpt for document',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $document->manuallyValidate(ManualValidationStatus::REFUSED, $user);

        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: $documentId,
            action: ManualValidationStatus::UNCERTAIN,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);

        $this->assertEquals(1, $this->documentValidationGateway->count());
        $validations = $this->documentValidationGateway->getAll();
        $this->assertEquals($documentId, $validations[0]->getDocumentId()->toString());
        $this->assertEquals($this->getUserId($user), $validations[0]->getUser()->getId());
        $this->assertEquals(ManualValidationStatus::UNCERTAIN, $validations[0]->getAction());

        $savedDocument = $this->documentGateway->get($documentId);
        $this->assertNull($savedDocument->getManualStatus());
        $this->assertNull($savedDocument->getValidatedBy());
        $this->assertNull($savedDocument->getValidatedAt());
    }

    public function testHandleResetValidationFromAcceptedToUncertain(): void
    {
        $documentId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User('550e8400-e29b-41d4-a716-446655440007', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            $documentId,
            'Test Document',
            'Test excerpt for document',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $document->manuallyValidate(ManualValidationStatus::ACCEPTED, $user);

        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: $documentId,
            action: ManualValidationStatus::UNCERTAIN,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);

        $savedDocument = $this->documentGateway->get($documentId);
        $this->assertNull($savedDocument->getManualStatus());
        $this->assertNull($savedDocument->getValidatedBy());
        $this->assertNull($savedDocument->getValidatedAt());
    }
}
