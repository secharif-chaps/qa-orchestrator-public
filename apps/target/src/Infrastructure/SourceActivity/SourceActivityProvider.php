<?php

declare(strict_types=1);

namespace App\Infrastructure\SourceActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\SourceActivity\GroupedSourceActivityDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<GroupedSourceActivityDto>
 */
class SourceActivityProvider implements ProviderInterface
{
    public function __construct(
        private readonly SourceActivityGatewayInterface $sourceActivityGateway,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly Security $security,
        private readonly Pagination $pagination,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): GroupedSourceActivityDto {
        $sourceId = $this->extractSourceId($uriVariables);
        $source = $this->getSource($sourceId);
        $this->validateAccess($source);

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        $activitiesByDay = $this->sourceActivityGateway->getBySourceGroupedByDay($source, $page, $limit);

        return new GroupedSourceActivityDto($activitiesByDay);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function extractSourceId(array $uriVariables): string
    {
        $sourceId = $uriVariables['sourceId'] ?? null;
        Assert::notNull($sourceId, 'Source ID must be provided');
        Assert::stringNotEmpty($sourceId, 'Source ID must be a non-empty string');

        return (string) $sourceId;
    }

    private function getSource(string $sourceId): Source
    {
        try {
            return $this->sourceGateway->get($sourceId);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Source not found', $e);
        }
    }

    private function validateAccess(Source $source): void
    {
        $watchFile = $source->getWatchFile();

        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to this watchfile.');
        }
    }
}
