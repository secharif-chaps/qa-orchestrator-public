<?php

declare(strict_types=1);

namespace App\Tests\Integration;

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
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ManualValidateDocumentIntegrationTest extends KernelTestCase
{
    use UserTestHelperTrait;
    private ManualValidateDocumentHandler $handler;
    private NullDocumentGateway $documentGateway;
    private NullDocumentValidationGateway $documentValidationGateway;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private NullUserGateway $userGateway;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

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

    public function testManualValidationIdempotenceCreatesMultipleAuditRecords(): void
    {
        $documentId = '550e8400-e29b-41d4-a716-446655440005';
        $user = new User('550e8400-e29b-41d4-a716-446655440016', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);

        $document = new Document(
            id: $documentId,
            title: 'Document for Idempotence Test',
            excerpt: 'Testing idempotent validation.',
            type: 'pdf',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content for idempotence',
            status: DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $this->documentGateway->save($document);
        $this->userGateway->save($user);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $action = new ManualValidateDocumentAction(
            documentId: $documentId,
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user)
        );

        ($this->handler)($action);
        ($this->handler)($action);

        $this->assertEquals(2, $this->documentValidationGateway->count());

        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertEquals(ManualValidationStatus::ACCEPTED, $updatedDocument->getManualStatus());
        $this->assertEquals($user->getId(), $updatedDocument->getValidatedBy()?->getId());
        $this->assertNotNull($updatedDocument->getValidatedAt());
    }
}
