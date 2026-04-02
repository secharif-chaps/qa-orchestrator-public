<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Document\ManualValidateDocumentAction;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\User\User;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Document\ManualValidateDocumentInputDto;
use App\UserInterface\Dto\Document\ManualValidateDocumentOutputDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<ManualValidateDocumentInputDto, ManualValidateDocumentOutputDto>
 */
class ManualValidateDocumentProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly Security $security,
    ) {
        $this->messageBus = $messageBus;
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ManualValidateDocumentOutputDto {
        $documentId = $uriVariables['id'] ?? null;
        Assert::stringNotEmpty($documentId, 'Document ID is required.');
        Assert::uuid($documentId, 'Document ID must be a valid UUID.');

        $inputDto = $this->extractInputDto($data, $context);

        $validationStatus = $inputDto->getManualValidationStatus();

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User must be authenticated.');
        }

        try {
            $document = $this->documentGateway->get($documentId);
        } catch (DocumentNotFoundException) {
            throw new NotFoundHttpException(\sprintf('Document with ID "%s" not found.', $documentId));
        }

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $document)) {
            throw new AccessDeniedHttpException('You do not have permission to validate this document.');
        }

        $userId = $user->getId();
        Assert::stringNotEmpty($userId, 'User ID must not be empty');

        $validationAction = new ManualValidateDocumentAction(
            documentId: $documentId,
            action: $validationStatus,
            validatedByUserId: $userId,
        );

        $this->handle($validationAction);

        $updatedDocument = $this->documentGateway->get($documentId);

        return new ManualValidateDocumentOutputDto(
            success: true,
            document_id: $updatedDocument->getId(),
            manual_status: $updatedDocument->getManualStatus()?->value,
            validated_by: $updatedDocument->getValidatedBy()?->getDisplayName(),
            validated_at: $updatedDocument->getValidatedAt(),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractInputDto(mixed $data, array $context): ManualValidateDocumentInputDto
    {
        if ($data instanceof ManualValidateDocumentInputDto) {
            return $data;
        }

        Assert::isInstanceOf($data, Document::class, 'Expected Document entity when DTO is not provided');

        /** @var Request|null $request */
        $request = $context['request'] ?? null;
        $payload = [];
        if ($request instanceof Request) {
            $content = (string) $request->getContent();
            $payload = '' !== $content ? (json_decode($content, true) ?: []) : [];
        }

        Assert::isArray($payload, 'Request payload must be an array');
        $action = $payload['action'] ?? null;
        Assert::string($action, 'validation.action.not_blank');
        Assert::stringNotEmpty(trim($action), 'validation.action.not_blank');

        return new ManualValidateDocumentInputDto($action);
    }
}
