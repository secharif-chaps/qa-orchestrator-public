<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Actor;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Actor\ActorNotFoundException;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;

class NullActorGateway implements ActorGatewayInterface
{
    /**
     * @var array<string, Actor>
     */
    private array $actors = [];
    private ?NullWatchFileGateway $watchFileGateway = null;

    public function setWatchFileGateway(NullWatchFileGateway $watchFileGateway): void
    {
        $this->watchFileGateway = $watchFileGateway;
    }

    public function getByLabel(string $label): Actor
    {
        foreach ($this->actors as $actor) {
            if ($actor->getLabel() === $label) {
                return $actor;
            }
        }
        throw new ActorNotFoundException(\sprintf('Actor with label %s not found', $label));
    }

    /**
     * @param array<string> $labels
     *
     * @return array<string, Actor>
     */
    public function getByLabels(array $labels): array
    {
        $result = [];
        foreach ($this->actors as $actor) {
            if (\in_array($actor->getLabel(), $labels, true)) {
                $result[$actor->getLabel()] = $actor;
            }
        }

        return $result;
    }

    public function get(string $id): Actor
    {
        if (!isset($this->actors[$id])) {
            throw new ActorNotFoundException(\sprintf('Actor with id %s not found', $id));
        }

        return $this->actors[$id];
    }

    public function findByIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->actors[$id])) {
                $result[$id] = $this->actors[$id];
            }
        }

        return $result;
    }

    public function save(Actor $actor): void
    {
        $reflection = new \ReflectionClass($actor);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);

        if (null === $idProperty->getValue($actor)) {
            $idProperty->setValue($actor, 'test-actor-id');
        }

        $this->actors[$actor->getId()] = $actor;
    }

    public function countByWatchFileId(string $watchFileId): int
    {
        // We use phpunit mock to test the countByWatchFileId method
        return 0;
    }

    public function findByPrimaryDomain(string $primaryDomain): ?Actor
    {
        foreach ($this->actors as $actor) {
            if ($actor->getPrimaryDomain() === $primaryDomain) {
                return $actor;
            }
        }

        return null;
    }

    public function getByWatchFileAndId(string $actorId, string $watchFileId): ?Actor
    {
        // Check if actor exists
        if (!isset($this->actors[$actorId])) {
            return null;
        }

        $actor = $this->actors[$actorId];

        // If we have access to WatchFileGateway, check if actor is linked to WatchFile
        if (null !== $this->watchFileGateway) {
            try {
                $watchFile = $this->watchFileGateway->get($watchFileId);
                foreach ($watchFile->getWatchFileActors() as $watchFileActor) {
                    if ($watchFileActor->getActor()->getId() === $actorId) {
                        return $actor;
                    }
                }
            } catch (\App\Domain\WatchFile\Exception\WatchFileNotFoundException) {
                return null;
            }
        }

        // If no WatchFileGateway is set, return null (not linked)
        return null;
    }
}
