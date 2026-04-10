<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Source\SourceGroup;
use App\UserInterface\Dto\Source\SourceGroupOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<Source>
 */
readonly class WatchFileGroupedSourceProvider implements ProviderInterface
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private SourceGatewayInterface $sourceGateway,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<SourceGroupOutput>|SourceGroupOutput|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable|object|null
    {
        if (isset($uriVariables['watchFileId']) && \is_string($uriVariables['watchFileId'])) {
            // Check if the watch file exists, throws an exception otherwise
            $watchFile = $this->watchFileGateway->get($uriVariables['watchFileId']);
            if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
                throw new AccessDeniedException('You do not have access to this watch file.');
            }
            // Extract search query from request
            $searchQuery = $this->extractSearchQuery($context);

            $groups = $this->sourceGateway->sourcesGrouped($watchFile, $searchQuery);
            $summary = [
                'total' => array_sum(array_map(fn (SourceGroup $group) => $group->getTotal(), $groups)),
                'error' => array_sum(array_map(fn (SourceGroup $group) => $group->getError(), $groups)),
                'running' => array_sum(array_map(fn (SourceGroup $group) => $group->getRunning(), $groups)),
                'stopped' => array_sum(array_map(fn (SourceGroup $group) => $group->getStopped(), $groups)),
            ];

            return new SourceGroupOutput(groups: $groups, summary: $summary);
        }
        throw new WatchFileNotFoundException();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractSearchQuery(array $context): ?string
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            return null;
        }

        $searchQuery = $request->query->getString('search');

        if (\strlen($searchQuery) < 2) {
            return null;
        }

        return $searchQuery;
    }
}
