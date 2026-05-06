<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<WatchFileActivity>
 */
class WatchFileActivityProvider implements ProviderInterface
{
    public function __construct(
        private readonly WatchFileActivityGatewayInterface $watchFileActivityGateway,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly Security $security,
        private readonly Pagination $pagination,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @return PaginatorInterface<WatchFileActivity>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $watchFileId = $uriVariables['watchFileId'] ?? null;
        Assert::notNull($watchFileId, 'WatchFile ID must be provided');
        Assert::stringNotEmpty($watchFileId, 'WatchFile ID must be a non-empty string');

        try {
            $watchFile = $this->watchFileGateway->get((string) $watchFileId);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('WatchFile not found', $e);
        }

        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to this watchfile.');
        }
        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        return $this->watchFileActivityGateway->getByWatchFilePaginated($watchFile, (int) $page, (int) $limit);
    }
}
