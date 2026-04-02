<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\Source;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Source\ChangeSourceStatusAction;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Source\ChangeSourceStatusDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<ChangeSourceStatusDto, Source>
 */
class ChangeSourceStatusProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Source
    {
        if (!isset($uriVariables['watchFileId'])) {
            throw new BadRequestHttpException('WatchFile ID must be provided in the URL');
        }

        if (!isset($uriVariables['id'])) {
            throw new BadRequestHttpException('Source ID must be provided in the URL');
        }

        $watchFileId = $uriVariables['watchFileId'];
        if (!\is_string($watchFileId)) {
            throw new BadRequestHttpException('WatchFile ID must be a string');
        }

        $sourceId = $uriVariables['id'];
        if (!\is_string($sourceId)) {
            throw new BadRequestHttpException('Source ID must be a string');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new BadRequestHttpException('User must be authenticated');
        }

        $watchFile = $this->watchFileGateway->get($watchFileId);
        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedHttpException('You are not allowed to change the status of this source');
        }

        $action = new ChangeSourceStatusAction(
            watchFileId: $watchFileId,
            sourceId: $sourceId,
            status: $data->getStatus(),
            user: $user
        );

        /** @var Source $result */
        $result = $this->handle($action);

        return $result;
    }
}
