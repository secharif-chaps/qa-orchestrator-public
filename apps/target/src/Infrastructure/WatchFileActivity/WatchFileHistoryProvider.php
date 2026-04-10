<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Application\WatchFileActivity\GetWatchFileHistoryAction;
use App\Application\WatchFileActivity\GetWatchFileHistoryHandler;
use App\UserInterface\Dto\WatchFileActivity\GroupedWatchFileActivityDto;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<GroupedWatchFileActivityDto>
 */
class WatchFileHistoryProvider implements ProviderInterface
{
    public function __construct(
        private readonly GetWatchFileHistoryHandler $handler,
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
    ): GroupedWatchFileActivityDto {
        $watchFileId = $this->extractWatchFileId($uriVariables);
        [$page, $offset, $itemsPerPage] = $this->pagination->getPagination($operation, $context);

        $action = new GetWatchFileHistoryAction($watchFileId);
        $result = ($this->handler)($action, $page, $itemsPerPage);

        return $result;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function extractWatchFileId(array $uriVariables): string
    {
        $watchFileId = $uriVariables['watchFileId'] ?? null;
        Assert::notNull($watchFileId, 'WatchFile ID must be provided');
        Assert::stringNotEmpty($watchFileId, 'WatchFile ID must be a non-empty string');

        return (string) $watchFileId;
    }
}
