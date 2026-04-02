<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Document\BatchManualValidateDocumentAction;
use App\Domain\User\User;
use App\UserInterface\Dto\Document\BatchManualValidateDocumentInputDto;
use App\UserInterface\Dto\Document\BatchManualValidateDocumentOutputDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<BatchManualValidateDocumentInputDto, BatchManualValidateDocumentOutputDto>
 */
class BatchManualValidateDocumentProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private Security $security,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param BatchManualValidateDocumentInputDto $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): BatchManualValidateDocumentOutputDto {
        $inputDto = $this->extractInputDto($data, $context);

        $validationStatus = $inputDto->getManualValidationStatus();

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User must be authenticated.');
        }

        $userId = $user->getId();
        Assert::stringNotEmpty($userId, 'User ID must not be empty');

        $validationAction = new BatchManualValidateDocumentAction(
            documentIds: $inputDto->documentIds,
            action: $validationStatus,
            validatedByUserId: $userId,
        );

        /** @var BatchManualValidateDocumentOutputDto $output */
        $output = $this->handle($validationAction);

        return $output;
    }

    /**
     * Extract input DTO from either DTO or Document entity.
     * API Platform can pass either depending on configuration.
     */
    /**
     * @param array<string, mixed> $context
     */
    private function extractInputDto(mixed $data, array $context): BatchManualValidateDocumentInputDto
    {
        if ($data instanceof BatchManualValidateDocumentInputDto) {
            return $data;
        }

        /** @var Request|null $request */
        $request = $context['request'] ?? null;
        $payload = [];
        if ($request instanceof Request) {
            $content = (string) $request->getContent();
            $payload = '' !== $content ? (json_decode($content, true) ?: []) : [];
        }

        Assert::isArray($payload, 'Request payload must be an array');
        $documentIds = $payload['document_ids'] ?? null;
        $action = $payload['action'] ?? null;

        Assert::isArray($documentIds, 'document_ids must be an array');
        Assert::string($action, 'validation.action.not_blank');
        Assert::stringNotEmpty(trim($action), 'validation.action.not_blank');

        return new BatchManualValidateDocumentInputDto($documentIds, $action);
    }
}
