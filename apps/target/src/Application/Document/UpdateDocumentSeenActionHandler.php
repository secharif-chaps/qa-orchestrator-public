<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentSeenStatus;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\Document\Exception\DocumentWithoutWatchFileException;
use App\Domain\User\UserGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class UpdateDocumentSeenActionHandler
{
    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly DocumentSeenStatusGatewayInterface $documentSeenStatusGateway,
        private readonly UserGatewayInterface $userGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(UpdateDocumentSeenAction $action): DocumentSeenStatus
    {
        $documentIdString = $action->documentId->toString();
        $userIdString = $action->userId->toString();

        $document = $this->documentGateway->get($documentIdString);
        $user = $this->userGateway->get($userIdString);

        $watchFile = $document->getWatchFile();
        if (null === $watchFile) {
            $this->logger?->error('UpdateDocumentSeenActionHandler: Document has no watch file', [
                'documentId' => $documentIdString,
            ]);

            throw DocumentWithoutWatchFileException::forDocumentId($documentIdString);
        }

        $existingDocumentSeenStatus = $this->documentSeenStatusGateway->findByUserAndDocument($user, $documentIdString);

        if (null !== $existingDocumentSeenStatus) {
            $existingDocumentSeenStatus->setSeenAt(new \DateTimeImmutable());
            $this->documentSeenStatusGateway->save($existingDocumentSeenStatus);

            return $existingDocumentSeenStatus;
        }

        $documentSeenStatus = new DocumentSeenStatus();
        $documentSeenStatus->setUser($user);
        $documentSeenStatus->setDocumentId(Uuid::fromString($documentIdString));
        $documentSeenStatus->setWatchFile($watchFile);

        $this->documentSeenStatusGateway->save($documentSeenStatus);

        return $documentSeenStatus;
    }
}
