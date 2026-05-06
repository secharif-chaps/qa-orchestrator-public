<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<WatchFileUser>
 */
class WatchFileUserProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
    ) {
    }

    /**
     * @return list<WatchFileUser>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        Assert::keyExists($uriVariables, 'watchFileId', 'WatchFile ID is required.');
        Assert::string($uriVariables['watchFileId'], 'WatchFile ID must be a string.');
        $watchFileId = $uriVariables['watchFileId'];

        Assert::true(
            Uuid::isValid($watchFileId),
            \sprintf('The watchfile ID "%s" is not a valid UUID.', $watchFileId)
        );

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('The user must be authenticated.');
        }

        $watchFile = $this->watchFileGateway->getForUser($watchFileId, $user);

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedException('The user must be granted access to the watchfile.');
        }

        return $this->watchFileUserGateway->getByWatchFile($watchFile);
    }
}
