<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\UsageLimit\ResourceCount;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;

class NullWatchFileGateway implements WatchFileGatewayInterface
{
    /**
     * @var array<string, WatchFile>
     */
    private array $watchFiles = [];
    private int $nextId = 1;

    public function get(string $id, ?User $user = null): WatchFile
    {
        if (!isset($this->watchFiles[$id])) {
            throw new WatchFileNotFoundException(\sprintf('WatchFile with id %s not found', $id));
        }

        return $this->watchFiles[$id];
    }

    public function save(WatchFile $watchFile): void
    {
        // Set an ID if it's null (for testing purposes)
        $reflection = new \ReflectionClass($watchFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);

        if (null === $idProperty->getValue($watchFile)) {
            if (1 === $this->nextId) {
                $idProperty->setValue($watchFile, 'test-watch-file-id');
                ++$this->nextId;
            } else {
                $idProperty->setValue($watchFile, \sprintf('test-watch-file-id-%d', $this->nextId++));
            }
        }

        $this->watchFiles[$watchFile->getId()] = $watchFile;
    }

    public function getForUser(string $id, User $user): WatchFile
    {
        if (!isset($this->watchFiles[$id])) {
            throw new WatchFileNotFoundException(\sprintf('WatchFile with id %s not found', $id));
        }

        $watchFile = $this->watchFiles[$id];
        foreach ($watchFile->getWatchFileUsers() as $watchFileUser) {
            if ($watchFileUser->getUser()?->getId() === $user->getId()) {
                return $watchFile;
            }
        }

        throw new WatchFileNotFoundException(\sprintf(
            'WatchFile with id %s not found for user %s',
            $id,
            $user->getId()
        ));
    }

    public function findAllIdsByUser(User $user): array
    {
        $ids = [];
        foreach ($this->watchFiles as $watchFile) {
            foreach ($watchFile->getWatchFileUsers() as $watchFileUser) {
                if ($watchFileUser->getUser()?->getId() === $user->getId()) {
                    $ids[] = $watchFile->getId();
                }
            }
        }

        return $ids;
    }

    public function countActiveByUserId(string $userId): ResourceCount
    {
        $count = 0;
        foreach ($this->watchFiles as $watchFile) {
            if (!$watchFile->isActive()) {
                continue;
            }

            foreach ($watchFile->getWatchFileUsers() as $watchFileUser) {
                if ($watchFileUser->getUser()?->getId() === $userId
                    && WatchFileUserRole::OWNER === $watchFileUser->getRole()) {
                    ++$count;
                    break;
                }
            }
        }

        return ResourceCount::fromInt($count);
    }

    public function countNonArchivedByOwnerId(string $userId): int
    {
        $count = 0;
        foreach ($this->watchFiles as $watchFile) {
            // Check if the user is an OWNER of this watchfile and it's not archived
            foreach ($watchFile->getWatchFileUsers() as $watchFileUser) {
                if ($watchFileUser->getUser()?->getId() === $userId
                    && WatchFileUserRole::OWNER === $watchFileUser->getRole()
                    && WatchFileStatus::ARCHIVED !== $watchFile->getStatus()
                ) {
                    ++$count;
                    break; // Count once per watchfile
                }
            }
        }

        return $count;
    }

    public function hasActorRelation(string $watchFileId, string $actorId): bool
    {
        if (!isset($this->watchFiles[$watchFileId])) {
            return false;
        }

        $watchFile = $this->watchFiles[$watchFileId];
        foreach ($watchFile->getWatchFileActors() as $watchFileActor) {
            if ($watchFileActor->getActor()->getId() === $actorId) {
                return true;
            }
        }

        return false;
    }
}
