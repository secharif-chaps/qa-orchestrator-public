<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Source\BatchChangeSourceStatusAction;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Source\BatchChangeSourceDataDto;
use App\UserInterface\Dto\Source\BatchChangeSourceStatusInputDto;
use App\UserInterface\Dto\Source\BatchChangeSourceStatusOutputDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<BatchChangeSourceStatusInputDto, BatchChangeSourceStatusOutputDto>
 */
class BatchChangeSourceStatusProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly Security $security,
    ) {
    }

    /**
     * @param BatchChangeSourceStatusInputDto|null $data
     * @param array<string, mixed>                 $uriVariables
     * @param array<string, mixed>                 $context
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): BatchChangeSourceStatusOutputDto {
        if (null === $data) {
            throw new BadRequestHttpException('Request body is required');
        }

        if (!isset($uriVariables['watchFileId'])) {
            throw new BadRequestHttpException('WatchFile ID must be provided in the URL');
        }

        $watchFileId = $uriVariables['watchFileId'];
        if (!\is_string($watchFileId)) {
            throw new BadRequestHttpException('WatchFile ID must be a string');
        }

        if (!Uuid::isValid($watchFileId)) {
            throw new BadRequestHttpException('WatchFile ID must be a valid UUID');
        }

        // Check access to the watch file
        $watchFile = $this->watchFileGateway->get($watchFileId);
        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to modify this watch file.');
        }

        // Normalize null to empty array for handler
        $sourcesArray = $data->sources ?? [];
        $sources = array_map(function ($source) use ($watchFileId) {
            return new BatchChangeSourceDataDto(id: $source['id'], watchFileId: $watchFileId);
        }, $sourcesArray);

        $action = new BatchChangeSourceStatusAction(sources: $sources, status: $data->getStatus());

        /** @var array{success: bool, message: string, results: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, total: int, processed: int, failed: int} $result */
        $result = $this->handle($action);

        return new BatchChangeSourceStatusOutputDto(
            success: $result['success'],
            message: $result['message'],
            results: $result['results'],
            errors: $result['errors'],
            total: $result['total'],
            processed: $result['processed'],
            failed: $result['failed'],
        );
    }
}
