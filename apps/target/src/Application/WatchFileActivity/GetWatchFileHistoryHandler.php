<?php

declare(strict_types=1);

namespace App\Application\WatchFileActivity;

use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\WatchFileActivity\GroupedWatchFileActivityDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

readonly class GetWatchFileHistoryHandler
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private WatchFileActivityGatewayInterface $watchFileActivityGateway,
        private Security $security,
    ) {
    }

    public function __invoke(
        GetWatchFileHistoryAction $action,
        int $page,
        int $itemsPerPage,
    ): GroupedWatchFileActivityDto {
        $this->validateAction($action);

        $watchFile = $this->getWatchFile($action->watchFileId);
        $this->validateAccess($watchFile);

        $result = $this->watchFileActivityGateway->getByWatchFileGroupedByDay($watchFile, $page, $itemsPerPage);

        return new GroupedWatchFileActivityDto($result['activitiesByDay'], $result['hasNextPage']);
    }

    private function validateAction(GetWatchFileHistoryAction $action): void
    {
        Assert::stringNotEmpty($action->watchFileId, 'WatchFile ID cannot be empty');
        Assert::uuid($action->watchFileId, 'WatchFile ID must be a valid UUID');
    }

    private function getWatchFile(string $watchFileId): WatchFile
    {
        try {
            return $this->watchFileGateway->get($watchFileId);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('WatchFile not found', $e);
        }
    }

    private function validateAccess(WatchFile $watchFile): void
    {
        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to this watch file.');
        }
    }
}
