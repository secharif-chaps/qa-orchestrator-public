<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Domain\User\UserGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\User\ConnectedUserVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<WatchFileActivity>
 */
class UserActivityProvider implements ProviderInterface
{
    public function __construct(
        private readonly WatchFileActivityGatewayInterface $watchFileActivityGateway,
        private readonly UserGatewayInterface $userGateway,
        private readonly Pagination $pagination,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @return PaginatorInterface<WatchFileActivity>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $userId = $uriVariables['userId'] ?? null;
        Assert::notNull($userId, 'User ID must be provided');
        Assert::stringNotEmpty($userId, 'User ID must be a non-empty string');

        $user = $this->userGateway->get((string) $userId);
        if (!$this->security->isGranted(ConnectedUserVoter::CONNECTED_USER, $user)) {
            throw new AccessDeniedHttpException('You do not have access to this user.');
        }

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        return $this->watchFileActivityGateway->getByUserPaginated($user, (int) $page, (int) $limit);
    }
}
