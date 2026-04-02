<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CheckWatchFileOwnerQuotaHandler
{
    public function __construct(
        private WatchFileGatewayInterface $watchFileGateway,
        private UsageLimitConfigInterface $usageLimitConfig,
    ) {
    }

    public function __invoke(CheckWatchFileOwnerQuotaAction $action): void
    {
        $quotaLimit = $this->usageLimitConfig->watchFileMaxOwnedNonArchived();

        if ($quotaLimit->isUnlimited()) {
            return;
        }

        $currentCount = $this->watchFileGateway->countNonArchivedByOwnerId($action->userId->toString());

        $resourceCount = ResourceCount::fromInt($currentCount);

        if ($resourceCount->exceeds($quotaLimit)) {
            throw QuotaExceededException::forWatchFileOwner($currentCount, $quotaLimit->value() ?? 0);
        }
    }
}
