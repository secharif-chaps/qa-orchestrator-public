<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\UpdateDocumentSeenAction;
use App\Application\Document\UpdateDocumentSeenActionHandler;
use App\Domain\Actor\Actor;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentSeenStatus;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\DocumentWithoutWatchFileException;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UpdateDocumentSeenActionHandlerTest extends TestCase
{
    private UpdateDocumentSeenActionHandler $handler;
    private DocumentGatewayInterface&MockObject $documentGateway;
    private DocumentSeenStatusGatewayInterface&Stub $documentSeenStatusGateway;
    private UserGatewayInterface&Stub $userGateway;

    protected function setUp(): void
    {
        $this->documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $this->documentSeenStatusGateway = $this->createStub(DocumentSeenStatusGatewayInterface::class);
        $this->userGateway = $this->createStub(UserGatewayInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new UpdateDocumentSeenActionHandler(
            $this->documentGateway,
            $this->documentSeenStatusGateway,
            $this->userGateway,
        );
    }

    public function testInvokeSuccessfullyCreatesDocumentSeenStatusWhenNoneExists(): void
    {
        $documentSeenStatusGatewayMock = $this->createMock(DocumentSeenStatusGatewayInterface::class);
        $this->documentSeenStatusGateway = $documentSeenStatusGatewayMock;
        $userGatewayMock = $this->createMock(UserGatewayInterface::class);
        $this->userGateway = $userGatewayMock;
        $this->buildHandler();
        $documentId = Uuid::v4();
        $userId = Uuid::v4();

        $user = $this->createTestUser($userId);
        $document = $this->createTestDocument($documentId);

        $action = new UpdateDocumentSeenAction($documentId, $userId);

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId->toString())
            ->willReturn($document);

        $userGatewayMock->expects($this->once())
            ->method('get')
            ->with($userId->toString())
            ->willReturn($user);

        $documentSeenStatusGatewayMock->expects($this->once())
            ->method('findByUserAndDocument')
            ->with($user, $documentId->toString())
            ->willReturn(null);

        $documentSeenStatusGatewayMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (DocumentSeenStatus $status) use ($user, $documentId, $document) {
                return $status->getUser() === $user
                    && $status->getDocumentId()?->equals($documentId)
                    && $status->getWatchFile() === $document->getWatchFile()
                    && $status->getSeenAt() instanceof \DateTimeImmutable;
            }));

        $result = ($this->handler)($action);

        $this->assertInstanceOf(DocumentSeenStatus::class, $result);
        $this->assertSame($user, $result->getUser());
        $this->assertTrue($result->getDocumentId()?->equals($documentId));
        $this->assertSame($document->getWatchFile(), $result->getWatchFile());
    }

    public function testInvokeSuccessfullyUpdatesExistingDocumentSeenStatus(): void
    {
        $documentSeenStatusGatewayMock = $this->createMock(DocumentSeenStatusGatewayInterface::class);
        $this->documentSeenStatusGateway = $documentSeenStatusGatewayMock;
        $userGatewayMock = $this->createMock(UserGatewayInterface::class);
        $this->userGateway = $userGatewayMock;
        $this->buildHandler();
        $documentId = Uuid::v4();
        $userId = Uuid::v4();

        $user = $this->createTestUser($userId);
        $document = $this->createTestDocument($documentId);

        // Create an existing DocumentSeenStatus
        $existingDocumentSeenStatus = new DocumentSeenStatus();
        $existingDocumentSeenStatus->setUser($user);
        $existingDocumentSeenStatus->setDocumentId($documentId);
        $existingDocumentSeenStatus->setWatchFile($document->getWatchFile());
        $existingDocumentSeenStatus->setSeenAt(new \DateTimeImmutable('2023-01-01 12:00:00'));

        $action = new UpdateDocumentSeenAction($documentId, $userId);

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId->toString())
            ->willReturn($document);

        $userGatewayMock->expects($this->once())
            ->method('get')
            ->with($userId->toString())
            ->willReturn($user);

        $documentSeenStatusGatewayMock->expects($this->once())
            ->method('findByUserAndDocument')
            ->with($user, $documentId->toString())
            ->willReturn($existingDocumentSeenStatus);

        $documentSeenStatusGatewayMock->expects($this->once())
            ->method('save')
            ->with($existingDocumentSeenStatus);

        $result = ($this->handler)($action);

        $this->assertInstanceOf(DocumentSeenStatus::class, $result);
        $this->assertSame($existingDocumentSeenStatus, $result);
        $this->assertSame($user, $result->getUser());
        $this->assertSame($documentId, $result->getDocumentId());
        $this->assertSame($document->getWatchFile(), $result->getWatchFile());
        $this->assertGreaterThan(new \DateTimeImmutable('2023-01-01 12:00:00'), $result->getSeenAt());
    }

    public function testInvokeThrowsExceptionWhenDocumentNotFound(): void
    {
        $documentId = Uuid::v4();
        $userId = Uuid::v4();
        $action = new UpdateDocumentSeenAction($documentId, $userId);

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId->toString())
            ->willThrowException(new DocumentNotFoundException('Document not found'));

        $this->expectException(DocumentNotFoundException::class);
        ($this->handler)($action);
    }

    public function testInvokeThrowsExceptionWhenDocumentHasNoWatchFile(): void
    {
        $userGatewayMock = $this->createMock(UserGatewayInterface::class);
        $this->userGateway = $userGatewayMock;
        $this->buildHandler();
        $documentId = Uuid::v4();
        $userId = Uuid::v4();

        $user = $this->createTestUser($userId);
        $document = $this->createTestDocumentWithoutWatchFile($documentId);

        $action = new UpdateDocumentSeenAction($documentId, $userId);

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId->toString())
            ->willReturn($document);

        $userGatewayMock->expects($this->once())
            ->method('get')
            ->with($userId->toString())
            ->willReturn($user);

        $this->expectException(DocumentWithoutWatchFileException::class);
        $this->expectExceptionMessage(
            'Document with ID "' . $documentId->toString() . '" has no associated watch file'
        );
        ($this->handler)($action);
    }

    private function createTestUser(Uuid $userId): User
    {
        return new User($userId->toString(), 'test@example.com', [], 'Test User');
    }

    private function createTestDocument(Uuid $documentId): Document
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'en' => 'Test description',
                'fr' => 'Description de test',
            ]),
            SourceType::RSS_FEED,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'en' => 'Test relevance',
                'fr' => 'Pertinence de test',
            ]),
            $actor
        );

        $document = new Document(
            $documentId->toString(),
            'Test Document',
            'Test excerpt',
            'html',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::VALIDATED,
            null,
            'en',
            false,
            null,
            false
        );
        $document->setWatchFile($watchFile);
        $document->setSource($source);
        $document->setActor($actor);

        return $document;
    }

    private function createTestDocumentWithoutWatchFile(Uuid $documentId): Document
    {
        return new Document(
            $documentId->toString(),
            'Test Document',
            'Test excerpt',
            'html',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::VALIDATED,
            null,
            'en',
            false,
            null,
            false
        );
    }
}
