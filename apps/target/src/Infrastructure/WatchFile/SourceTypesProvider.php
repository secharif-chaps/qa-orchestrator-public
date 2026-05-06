<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceStatus;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Source\SourceTypesDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<SourceTypesDto>
 */
class SourceTypesProvider implements ProviderInterface
{
    public function __construct(
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SourceTypesDto
    {
        if (!isset($uriVariables['watchFileId'])) {
            throw new BadRequestHttpException('WatchFile ID must be provided in the URL');
        }

        $watchFileId = $uriVariables['watchFileId'];
        if (!\is_string($watchFileId)) {
            throw new BadRequestHttpException('WatchFile ID must be a string');
        }

        if (!Uuid::isValid($watchFileId)) {
            throw new BadRequestHttpException('WatchFile ID must be a valid UUID');
        }

        // Check access to the watchfile
        $watchFile = $this->watchFileGateway->get($watchFileId);
        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to this watchfile.');
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $statusParam = $filters['status'] ?? null;
        $nameParam = $filters['name'] ?? null;
        $status = null;

        if (null !== $statusParam) {
            if (!\is_string($statusParam) && !\is_int($statusParam)) {
                throw new BadRequestHttpException('Invalid status value. Must be "active" or "inactive".');
            }
            $status = SourceStatus::tryFrom($statusParam);
            if (null === $status || !\in_array($status, [SourceStatus::ACTIVE, SourceStatus::INACTIVE], true)) {
                throw new BadRequestHttpException('Invalid status value. Must be "active" or "inactive".');
            }
        }

        $name = null;
        if (null !== $nameParam && \is_string($nameParam)) {
            $name = trim($nameParam);
            if ('' === $name) {
                $name = null;
            }
        }

        $typeCounts = $this->sourceGateway->countSourceTypesByWatchFile($watchFileId, $status, $name);

        return new SourceTypesDto($typeCounts);
    }
}
