<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileUserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullWatchFileUserGateway implements WatchFileUserGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, WatchFileUser>
     */
    public array $watchFileUsers = [];

    public function get(string $id): WatchFileUser
    {
        if (!isset($this->watchFileUsers[$id])) {
            throw new WatchFileUserNotFoundException();
        }

        return $this->watchFileUsers[$id];
    }

    public function getByWatchFileAndUser(WatchFile $watchFile, User $user): WatchFileUser
    {
        foreach ($this->watchFileUsers as $watchFileUser) {
            if ($watchFileUser->getWatchFile()?->getId() === $watchFile->getId() && $watchFileUser->getUser()?->getId() === $user->getId()) {
                return $watchFileUser;
            }
        }

        throw new WatchFileUserNotFoundException(\sprintf(
            'WatchFileUser not found for watch file %s and user %s',
            $watchFile->getId(),
            $user->getId()
        ));
    }

    public function save(WatchFileUser $watchFileUser): void
    {
        $this->watchFileUsers[$watchFileUser->getId()] = $watchFileUser;
    }

    public function getByWatchFile(WatchFile $watchFile): array
    {
        $watchFileUsers = [];
        foreach ($this->watchFileUsers as $watchFileUser) {
            if ($watchFileUser->getWatchFile()?->getId() === $watchFile->getId()) {
                $watchFileUsers[] = $watchFileUser;
            }
        }

        return $watchFileUsers;
    }

    public function remove(WatchFileUser $watchFileUser): void
    {
        unset($this->watchFileUsers[$watchFileUser->getId()]);
    }

    public function countByWatchFile(WatchFile $watchFile): int
    {
        $count = 0;
        foreach ($this->watchFileUsers as $watchFileUser) {
            if ($watchFileUser->getWatchFile()?->getId() === $watchFile->getId()) {
                ++$count;
            }
        }

        return $count;
    }

    public function countByWatchFiles(iterable $watchFiles): array
    {
        $results = [];
        foreach ($watchFiles as $watchFile) {
            $watchFileId = $watchFile->getId();
            $results[$watchFileId] = $this->countByWatchFile($watchFile);
        }

        return $results;
    }

    public function hasEditAccessForWatchFiles(User $user, iterable $watchFiles): array
    {
        $results = [];
        foreach ($watchFiles as $watchFile) {
            $watchFileId = $watchFile->getId();
            $results[$watchFileId] = $this->hasEditAccess($user, $watchFile);
        }

        return $results;
    }

    public function hasEditAccess(User $user, WatchFile $watchFile): bool
    {
        // Check if user has OWNER or EDITOR role in WatchFileUser relationship
        foreach ($this->watchFileUsers as $watchFileUser) {
            if ($watchFileUser->getUser()?->getId() === $user->getId()
                && $watchFileUser->getWatchFile()?->getId() === $watchFile->getId()
                && \in_array($watchFileUser->getRole(), [WatchFileUserRole::OWNER, WatchFileUserRole::EDITOR], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a WatchFileUser relationship for testing.
     */
    public function addWatchFileUser(WatchFile $watchFile, User $user, WatchFileUserRole $role): void
    {
        $watchFileUser = new WatchFileUser($watchFile, $user, $role);
        $this->forcePropertyValue($watchFileUser, 'wfu_' . Uuid::v4());
        $this->save($watchFileUser);
    }

    public function getUsersWithRealTimeAccess(WatchFile $watchFile): array
    {
        $users = [];
        foreach ($this->watchFileUsers as $watchFileUser) {
            $role = $watchFileUser->getRole();
            $user = $watchFileUser->getUser();
            if (null !== $role
                && null !== $user
                && $role->canReceiveRealTimeUpdates()
                && $watchFileUser->getWatchFile()?->getId() === $watchFile->getId()) {
                $users[] = $user;
            }
        }

        return $users;
    }
}
