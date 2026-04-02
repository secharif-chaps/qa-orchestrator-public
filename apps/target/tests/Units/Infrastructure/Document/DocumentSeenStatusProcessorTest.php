<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use App\Application\Document\UpdateDocumentSeenAction;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentSeenStatus;
use App\Domain\User\User;
use App\Infrastructure\Document\DocumentProvider;
use App\Infrastructure\Document\DocumentSeenStatusProcessor;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

class DocumentSeenStatusProcessorTest extends TestCase
{
    private DocumentSeenStatusProcessor $processor;
    private Security $security;
    private MessageBusInterface&Stub $messageBus;
    private DocumentProvider $documentProvider;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->documentProvider = $this->createStub(DocumentProvider::class);
        $this->operation = $this->createStub(Operation::class);
        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $this->processor = new DocumentSeenStatusProcessor($this->security, $this->messageBus, $this->documentProvider);
    }

    public function testProcessSuccessfullyMarksDocumentAsSeen(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $documentProviderMock = $this->createMock(DocumentProvider::class);
        $this->documentProvider = $documentProviderMock;
        $this->buildProcessor();
        $documentId = Uuid::v4();
        $userId = Uuid::v4();
        $user = new User($userId->toString(), 'test@example.com', [], 'Test User');

        $documentSeenStatus = new DocumentSeenStatus();
        $documentSeenStatus->setUser($user);
        $documentSeenStatus->setDocumentId($documentId);

        $document = new Document(
            $documentId->toString(),
            'Test Document',
            'Test excerpt',
            'html',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            \App\Domain\Document\DocumentStatus::VALIDATED,
            null,
            'en',
            false,
            null,
            false
        );

        $uriVariables = [
            'id' => $documentId->toString(),
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (UpdateDocumentSeenAction $action) use ($documentId, $userId) {
                return $action->documentId->equals($documentId) && $action->userId->equals($userId);
            }))
            ->willReturn(new Envelope($documentSeenStatus, [new HandledStamp($documentSeenStatus, 'handler')]));

        $documentProviderMock->expects($this->exactly(2))
            ->method('provide')
            ->willReturnOnConsecutiveCalls($document, $document);

        $result = $this->processor->process(null, $this->operation, $uriVariables, $context);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame($document, $result);
    }

    public function testProcessThrowsExceptionWhenDocumentIdIsMissing(): void
    {
        $uriVariables = [];
        $context = [];

        $this->expectException(\Webmozart\Assert\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document ID is required and must be a valid UUID.');
        $this->processor->process(null, $this->operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenDocumentIdIsInvalid(): void
    {
        $uriVariables = [
            'id' => 'invalid-uuid',
        ];
        $context = [];

        $this->expectException(\Webmozart\Assert\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document ID must be a valid UUID.');
        $this->processor->process(null, $this->operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenUserIsNotAuthenticated(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        $documentId = Uuid::v4();
        $uriVariables = [
            'id' => $documentId->toString(),
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(\Webmozart\Assert\InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must be authenticated.');
        $this->processor->process(null, $this->operation, $uriVariables, $context);
    }
}
