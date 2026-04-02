<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Document\UpdateDocumentSeenAction;
use App\Domain\Document\Document;
use App\Domain\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<mixed, Document>
 */
class DocumentSeenStatusProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private readonly Security $security,
        private MessageBusInterface $messageBus,
        private readonly DocumentProvider $documentProvider,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): Document {
        $documentId = $uriVariables['id'] ?? null;
        Assert::string($documentId, 'Document ID is required and must be a valid UUID.');
        Assert::uuid($documentId, 'Document ID must be a valid UUID.');

        $user = $this->security->getUser();
        Assert::isInstanceOf($user, User::class, 'The user must be authenticated.');

        $userId = $user->getId();
        Assert::notNull($userId, 'User ID is required.');

        $existingDocument = $this->documentProvider->provide($operation, $uriVariables, $context);
        if (null === $existingDocument) {
            throw new NotFoundHttpException('Document not found.');
        }

        $action = UpdateDocumentSeenAction::forCurrentUser(
            documentId: Uuid::fromString($documentId),
            currentUserId: Uuid::fromString($userId)
        );

        $this->handle($action);

        $updatedDocument = $this->documentProvider->provide($operation, $uriVariables, $context);
        if (null === $updatedDocument) {
            throw new NotFoundHttpException('Document not found.');
        }

        return $updatedDocument;
    }
}
