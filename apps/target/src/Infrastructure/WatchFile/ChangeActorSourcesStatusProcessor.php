<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Actor\ChangeActorStatusAction;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Actor\ChangeActorStatusInputDto;
use App\UserInterface\Dto\Actor\ChangeActorStatusOutputDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<ChangeActorStatusInputDto, ChangeActorStatusOutputDto>
 */
class ChangeActorSourcesStatusProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private Security $security,
        private WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    /**
     * @param ChangeActorStatusInputDto|null $data
     * @param array<string, mixed>           $uriVariables
     * @param array<string, mixed>           $context
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ChangeActorStatusOutputDto {
        if (null === $data) {
            throw new BadRequestHttpException('Request body is required');
        }

        if (!isset($uriVariables['watchFileId'])) {
            throw new BadRequestHttpException('Watch file ID must be provided in the URL');
        }

        if (!isset($uriVariables['actorId'])) {
            throw new BadRequestHttpException('Actor ID must be provided in the URL');
        }

        $watchFileId = $uriVariables['watchFileId'];
        if (!\is_string($watchFileId)) {
            throw new BadRequestHttpException('Watch file ID must be a string');
        }

        $actorId = $uriVariables['actorId'];
        if (!\is_string($actorId)) {
            throw new BadRequestHttpException('Actor ID must be a string');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new BadRequestHttpException('User must be authenticated');
        }

        $watchFile = $this->watchFileGateway->get($watchFileId);
        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedHttpException('User must have right edit on the watch file.');
        }

        $action = new ChangeActorStatusAction(
            watchFileId: $watchFileId,
            actorId: $actorId,
            sourceIds: $data->sourceIds,
            newStatus: $data->getStatus(),
            user: $user,
        );

        /** @var ChangeActorStatusOutputDto $result */
        $result = $this->handle($action);

        return $result;
    }
}
