<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Application\WatchFile\Share\RemoveShareWatchFileAction;
use App\Application\WatchFile\Share\ShareWatchFileAction;
use App\Application\WatchFile\Share\ShareWatchFileBatchAction;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\WatchFile\ShareWatchFileInputDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ShareWatchFileInputDto, list<WatchFileUser>>
 * @implements ProviderInterface<object>
 */
class WatchFileUserProcessor implements ProcessorInterface, ProviderInterface
{
    use HandleTrait;

    public function __construct(
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
        private readonly Security $security,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        return new \stdClass();
    }

    /**
     * @params ShareWatchFileInputDto|null $data
     *
     * @return list<WatchFileUser>
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $watchFileId = $uriVariables['watchFileId'] ?? null;
        if (!\is_string($watchFileId) || !Uuid::isValid($watchFileId)) {
            throw new \RuntimeException('WatchFile ID is required.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The user must be authenticated.');
        }

        try {
            $watchFile = $this->watchFileGateway->getForUser($watchFileId, $user);
        } catch (WatchFileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf(
                'The watchfile with ID %s was not found for the user.',
                $watchFileId
            ), $e, );
        }

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedHttpException('The user must be granted access to the watchfile.');
        }

        $userId = $user->getId();
        if (null === $userId) {
            throw new \RuntimeException('User ID is required.');
        }

        return match (true) {
            $operation instanceof Delete => $this->handleDelete(
                $watchFileId,
                $uriVariables['watchFileUserId'] ?? '',
                $userId
            ),
            $operation instanceof Post => $this->handlePost($watchFileId, $data, $userId),
            default => throw new \RuntimeException('Unsupported operation type: ' . $operation::class),
        };
    }

    /**
     * @params list<ShareWatchFileInputDto> $data
     *
     * @return list<WatchFileUser>
     */
    private function handlePost(string $watchFileId, ShareWatchFileInputDto $data, string $userId): array
    {
        $shareWatchFileActions = [];
        foreach ($data->member as $member) {
            try {
                $watchFileUserRole = WatchFileUserRole::from($member->role);

                $shareWatchFileActions[] = new ShareWatchFileAction($watchFileId, $member->userId, $watchFileUserRole);
            } catch (\ValueError $e) {
                throw new UnprocessableEntityHttpException('Invalid role provided: ' . $member->role, $e);
            }
        }

        if (empty($shareWatchFileActions)) {
            throw new UnprocessableEntityHttpException('At least one share action is required.');
        }

        /** @var list<WatchFileUser> $watchFileUsers */
        $watchFileUsers = $this->handle(new ShareWatchFileBatchAction($shareWatchFileActions, $userId));

        return $watchFileUsers;
    }

    /**
     * @params list<ShareWatchFileInputDto> $data
     *
     * @return list<WatchFileUser>
     */
    private function handleDelete(string $watchFileId, mixed $watchFileUserId, string $userId): array
    {
        if (empty($watchFileUserId) || !\is_string($watchFileUserId) || !Uuid::isValid($watchFileUserId)) {
            throw new BadRequestHttpException('WatchFile user ID is required.');
        }

        try {
            $watchFileUser = $this->watchFileUserGateway->get($watchFileUserId);
        } catch (\Exception $e) {
            throw new NotFoundHttpException(\sprintf('WatchFile user with ID "%s" not found.', $watchFileUserId), $e);
        }

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFileUser->getWatchFile())) {
            throw new AccessDeniedHttpException('You do not have permission to remove users from this watchfile.');
        }

        if (WatchFileUserRole::OWNER === $watchFileUser->getRole()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'The watchfile user "%s" is the owner and cannot be removed.',
                $watchFileUser->getId()
            ));
        }

        $this->messageBus->dispatch(new RemoveShareWatchFileAction($watchFileId, $watchFileUserId, $userId));

        return []; // Assuming the deletion does not return any WatchFileUser objects.
    }
}
