<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Document\Document;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<Document>
 */
readonly class DocumentProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Document> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.elasticsearch.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly EntityEnrichmentOrchestratorInterface $entityEnrichmentOrchestrator,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Document
    {
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$result instanceof Document) {
            return null;
        }

        // Check if user has permission to view the watch file that this document belongs to
        if (null === $result->getWatchFile() || !$this->security->isGranted(
            WatchFileVoter::VIEW,
            $result->getWatchFile()
        )) {
            throw new AccessDeniedException('You do not have permission to view this document.');
        }

        return $this->entityEnrichmentOrchestrator->enrich($result, [
            'user' => $this->security->getUser(),
        ]);
    }
}
