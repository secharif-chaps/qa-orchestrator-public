<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Source;

use App\Domain\Actor\Actor;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceNotFoundException;
use App\Domain\Source\SourceStatus;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\WatchFile\WatchFile;
use App\UserInterface\Dto\Source\SourceGroup;
use Doctrine\Common\Collections\ArrayCollection;

class NullSourceGateway implements SourceGatewayInterface
{
    /** @var Source[] */
    private array $sources = [];

    public function get(string $id): Source
    {
        foreach ($this->sources as $source) {
            if ($source->getId() === $id) {
                return $source;
            }
        }
        throw new SourceNotFoundException(\sprintf('Source with id %s not found', $id));
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, Source>
     */
    public function findByIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            foreach ($this->sources as $source) {
                if ($source->getId() === $id) {
                    $result[$id] = $source;
                    break;
                }
            }
        }

        return $result;
    }

    public function alreadyExist(WatchFile $watchFile, Source $source): bool
    {
        foreach ($this->sources as $existingSource) {
            if (
                $existingSource->getWatchFile() === $watchFile
                && $existingSource->getType() === $source->getType()
                && $existingSource->getUrl() === $source->getUrl()
            ) {
                return true;
            }
        }

        return false;
    }

    public function countSourcesForWatchFile(WatchFile $watchFile): ResourceCount
    {
        $count = 0;
        foreach ($this->sources as $source) {
            if ($source->getWatchFile() === $watchFile) {
                ++$count;
            }
        }

        return ResourceCount::fromInt($count);
    }

    public function save(Source $source): void
    {
        $this->sources[] = $source;
    }

    public function updateSourcesStatus(array $sources, SourceStatus $status): void
    {
        foreach ($sources as $source) {
            $source->setStatus($status);
        }
    }

    public function findByActor(Actor|string $actor): array
    {
        $result = [];
        foreach ($this->sources as $source) {
            if ($source->getActor() === $actor || $source->getActor()?->getId() === $actor) {
                $result[] = $source;
            }
        }

        return $result;
    }

    public function findByActorAndWatchFile(Actor|string $actor, WatchFile $watchFile): array
    {
        $result = [];
        foreach ($this->sources as $source) {
            if (
                ($source->getActor() === $actor || $source->getActor()?->getId() === $actor)
                && $source->getWatchFile() === $watchFile
            ) {
                $result[] = $source;
            }
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function countSourcesByActorForWatchFile(WatchFile|string $watchFile): array
    {
        $watchFileId = $watchFile instanceof WatchFile ? $watchFile->getId() : $watchFile;
        $counts = [];

        foreach ($this->sources as $source) {
            if ($source->getWatchFile()->getId() !== $watchFileId) {
                continue;
            }

            $actorId = $source->getActor()?->getId();
            if (null === $actorId) {
                continue;
            }

            $counts[$actorId] = ($counts[$actorId] ?? 0) + 1;
        }

        return $counts;
    }

    public function sourcesGrouped(WatchFile|string $watchFile, ?string $searchQuery = null): array
    {
        $watchFileId = $watchFile instanceof WatchFile ? $watchFile->getId() : $watchFile;
        $sources = array_filter($this->sources, function (Source $source) use ($watchFileId, $searchQuery) {
            // Filter by watchfile
            if ($source->getWatchFile()->getId() !== $watchFileId) {
                return false;
            }

            // Apply search filter if provided
            if ($searchQuery) {
                $nameMatch = false !== stripos($source->getName(), $searchQuery);
                $domainMatch = false !== stripos($source->getPrimaryDomain(), $searchQuery);

                return $nameMatch || $domainMatch;
            }

            return true;
        });
        $groupedSources = [];

        foreach ($sources as $source) {
            if (CollectStatus::ERROR === $source->getCollectStatus()) {
                $groupedSources['error'] ??= [];
                $groupedSources['error'][] = $source;
            } else {
                $groupedSources[$source->getType()->name] ??= [];
                $groupedSources[$source->getType()->name][] = $source;
            }
        }

        $sourceGroups = [];
        if (!empty($groupedSources['error'])) {
            $sourceGroups[] = new SourceGroup('error', 'testerror', \count(
                $groupedSources['error']
            ), new ArrayCollection($groupedSources['error']));
            unset($groupedSources['error']);
        }

        foreach ($groupedSources as $type => $sources) {
            $sourceGroups[] = new SourceGroup($type, 'test' . $type, \count($sources), new ArrayCollection($sources));
        }

        return $sourceGroups;
    }

    /**
     * @param string[] $sourceIds
     *
     * @return array<string, string>
     */
    public function getPrimaryDomains(array $sourceIds = []): array
    {
        $result = [];
        foreach ($this->sources as $source) {
            if (\in_array($source->getId(), $sourceIds)) {
                $result[$source->getId()] = $source->getPrimaryDomain();
            }
        }

        return $result;
    }

    public function countActiveByWatchFile(WatchFile $watchFile): ResourceCount
    {
        $count = 0;
        foreach ($this->sources as $source) {
            if (!$source->isActive()) {
                continue;
            }

            if ($source->getWatchFile() === $watchFile) {
                ++$count;
            }
        }

        return ResourceCount::fromInt($count);
    }

    /**
     * @return array<string, int>
     */
    public function countSourceTypesByWatchFile(
        string $watchFileId,
        ?SourceStatus $status = null,
        ?string $name = null,
    ): array {
        $filteredSources = array_filter($this->sources, function (Source $source) use ($watchFileId, $status, $name) {
            if ($source->getWatchFile()->getId() !== $watchFileId) {
                return false;
            }

            if (null !== $status) {
                if (SourceStatus::INACTIVE === $status) {
                    // Both INACTIVE and AUTO_DISABLED are considered inactive
                    if (!\in_array($source->getStatus(), [SourceStatus::INACTIVE, SourceStatus::AUTO_DISABLED], true)) {
                        return false;
                    }
                } elseif ($source->getStatus() !== $status) {
                    return false;
                }
            }

            if (null !== $name && '' !== $name) {
                $nameMatch = false !== stripos($source->getName(), $name);
                $domainMatch = false !== stripos($source->getPrimaryDomain(), $name);
                if (!$nameMatch && !$domainMatch) {
                    return false;
                }
            }

            return true;
        });

        // Count sources by type
        $typeCounts = [];
        foreach ($filteredSources as $source) {
            $typeValue = $source->getType()
->value;
            $typeCounts[$typeValue] = ($typeCounts[$typeValue] ?? 0) + 1;
        }

        return $typeCounts;
    }

    /**
     * Find orphaned sources (sources without actor) for a watchfile that match a given domain.
     */
    public function findOrphanedSourcesByDomain(WatchFile|string $watchFile, string $domain): array
    {
        $watchFileId = $watchFile instanceof WatchFile ? $watchFile->getId() : $watchFile;

        // Normalize domain: remove www prefix if present for comparison
        $normalizedDomain = $this->normalizeDomain($domain);
        $domainWithWww = 'www.' . $normalizedDomain;

        $result = [];
        foreach ($this->sources as $source) {
            // Filter by watchfile
            if ($source->getWatchFile()->getId() !== $watchFileId) {
                continue;
            }

            // Filter by orphaned (no actor)
            if (null !== $source->getActor()) {
                continue;
            }

            // Match domain with www variations
            $sourceDomain = strtolower($source->getPrimaryDomain());
            $normalizedDomainLower = strtolower($normalizedDomain);
            $domainWithWwwLower = strtolower($domainWithWww);

            if ($sourceDomain === $normalizedDomainLower || $sourceDomain === $domainWithWwwLower) {
                $result[] = $source;
            }
        }

        return $result;
    }

    /**
     * Normalize a domain by removing www prefix if present.
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $lowerDomain = strtolower($domain);

        // Remove www. prefix if present
        if (str_starts_with($lowerDomain, 'www.')) {
            return substr($domain, 4);
        }

        return $domain;
    }
}
