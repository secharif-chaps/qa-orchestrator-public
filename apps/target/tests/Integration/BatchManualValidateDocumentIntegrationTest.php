<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Document\BatchManualValidateDocumentAction;
use App\Application\Document\BatchManualValidateDocumentHandler;
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
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\UserTestHelperTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

class BatchManualValidateDocumentIntegrationTest extends KernelTestCase
{
    use EntityUtilsTrait;
    use UserTestHelperTrait;
    private BatchManualValidateDocumentHandler $handler;
    private NullDocumentGateway $documentGateway;
    private NullDocumentValidationGateway $documentValidationGateway;
    private NullUserGateway $userGateway;
    private Security&Stub $security;
    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->documentGateway = new NullDocumentGateway();
        $this->documentValidationGateway = new NullDocumentValidationGateway();
        $this->userGateway = new NullUserGateway();
        $this->security = $this->createStub(Security::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->security->method('isGrantedForUser')
            ->willReturn(true);

        $this->handler = new BatchManualValidateDocumentHandler(
            $this->documentGateway,
            $this->documentValidationGateway,
            $this->userGateway,
            $this->security,
            $this->eventDispatcher,
            new NullLogger(),
        );
    }

    public function testBatchValidationWithDocumentStateTransitions(): void
    {
        $this->eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user = new User('550e8400-e29b-41d4-a716-446655440130', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = [
            '550e8400-e29b-41d4-a716-446655440131',
            '550e8400-e29b-41d4-a716-446655440132',
            '550e8400-e29b-41d4-a716-446655440133',
        ];

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
        $this->documentGateway->save($doc1);

        $doc2 = new Document(
            $docIds[1],
            'Document 2',
            'Excerpt 2',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Content 2',
            DocumentStatus::VALIDATED,
        );
        $doc2->setWatchFile($watchFile);
        $this->documentGateway->save($doc2);

        $doc3 = new Document(
            $docIds[2],
            'Document 3',
            'Excerpt 3',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Content 3',
            DocumentStatus::REJECTED,
        );
        $doc3->setWatchFile($watchFile);
        $this->documentGateway->save($doc3);

        $this->userGateway->save($user);

        $action = new BatchManualValidateDocumentAction(
            documentIds: $docIds,
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user),
        );

        $result = ($this->handler)($action);

        $this->assertEquals(3, $result->validated_count);
        $this->assertEquals(0, $result->failed_count);
        $this->assertEquals('accept', $result->manual_status);
        $this->assertEquals($user->getDisplayName(), $result->validated_by);
        $this->assertNotEmpty($result->validated_at);
        $this->assertCount(3, $result->results);

        foreach ($result->results as $resultItem) {
            $this->assertEquals('success', $resultItem['status']);
            $this->assertArrayNotHasKey('manual_status', $resultItem);
            $this->assertArrayNotHasKey('validated_by', $resultItem);
            $this->assertArrayNotHasKey('validated_at', $resultItem);
        }

        $validations = $this->documentValidationGateway->getAll();
        $this->assertCount(3, $validations);

        foreach ($validations as $validation) {
            $this->assertEquals(ManualValidationStatus::ACCEPTED, $validation->getAction());
            $this->assertEquals($user->getId(), $validation->getUser()->getId());
        }
    }

    public function testBatchValidationWithConcurrentOperations(): void
    {
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(DocumentManuallyValidatedEvent::class));

        $user1 = new User('550e8400-e29b-41d4-a716-446655440140', 'user1@example.com', ['ROLE_USER']);
        $user2 = new User('550e8400-e29b-41d4-a716-446655440141', 'user2@example.com', ['ROLE_USER']);

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user1);
        $this->forcePropertyValue($watchFile, Uuid::v4()->toString());

        $docIds = ['550e8400-e29b-41d4-a716-446655440142', '550e8400-e29b-41d4-a716-446655440143'];

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

        $this->userGateway->save($user1);
        $this->userGateway->save($user2);

        $action1 = new BatchManualValidateDocumentAction(
            documentIds: [$docIds[0]],
            action: ManualValidationStatus::ACCEPTED,
            validatedByUserId: $this->getUserId($user1),
        );

        $action2 = new BatchManualValidateDocumentAction(
            documentIds: [$docIds[1]],
            action: ManualValidationStatus::REFUSED,
            validatedByUserId: $this->getUserId($user2),
        );

        $result1 = ($this->handler)($action1);
        $result2 = ($this->handler)($action2);

        $this->assertEquals(1, $result1->validated_count);
        $this->assertEquals(0, $result1->failed_count);
        $this->assertEquals('accept', $result1->manual_status);
        $this->assertEquals($user1->getDisplayName(), $result1->validated_by);
        $this->assertNotEmpty($result1->validated_at);
        $this->assertCount(1, $result1->results);
        $this->assertEquals('success', $result1->results[0]['status']);
        $this->assertArrayNotHasKey('manual_status', $result1->results[0]);
        $this->assertArrayNotHasKey('validated_by', $result1->results[0]);
        $this->assertArrayNotHasKey('validated_at', $result1->results[0]);

        $this->assertEquals(1, $result2->validated_count);
        $this->assertEquals(0, $result2->failed_count);
        $this->assertEquals('refuse', $result2->manual_status);
        $this->assertEquals($user2->getDisplayName(), $result2->validated_by);
        $this->assertNotEmpty($result2->validated_at);
        $this->assertCount(1, $result2->results);
        $this->assertEquals('success', $result2->results[0]['status']);
        $this->assertArrayNotHasKey('manual_status', $result2->results[0]);
        $this->assertArrayNotHasKey('validated_by', $result2->results[0]);
        $this->assertArrayNotHasKey('validated_at', $result2->results[0]);

        $this->assertEquals(2, $this->documentValidationGateway->count());

        $validations = $this->documentValidationGateway->getAll();
        $this->assertEquals(ManualValidationStatus::ACCEPTED, $validations[0]->getAction());
        $this->assertEquals(ManualValidationStatus::REFUSED, $validations[1]->getAction());
    }
}
