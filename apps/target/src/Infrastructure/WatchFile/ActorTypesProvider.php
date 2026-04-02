<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Actor\ActorStatus;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Actor\ActorTypesDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ActorTypesDto>
 */
class ActorTypesProvider implements ProviderInterface
{
    public function __construct(
        private readonly WatchFileActorGatewayInterface $watchFileActorGateway,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ActorTypesDto
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

        // Check access to the watch file
        $watchFile = $this->watchFileGateway->get($watchFileId);
        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to this watch file.');
        }

        // Get filters from query parameters
        $request = $this->requestStack->getCurrentRequest();
        $statusParam = $request?->query->get('status');
        $nameParam = $request?->query->get('name');
        $status = null;

        if (null !== $statusParam) {
            $status = ActorStatus::tryFrom($statusParam);
            if (null === $status) {
                throw new BadRequestHttpException('Invalid status value. Must be "active" or "inactive".');
            }
        }

        $name = null;
        if (null !== $nameParam) {
            $name = trim($nameParam);
            if ('' === $name) {
                $name = null;
            }
        }

        $types = $this->watchFileActorGateway->getActorTypesCounts($watchFileId, $status, $name);

        return new ActorTypesDto($types);
    }
}
