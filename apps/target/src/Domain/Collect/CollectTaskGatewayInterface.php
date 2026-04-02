<?php

declare(strict_types=1);

namespace App\Domain\Collect;

interface CollectTaskGatewayInterface
{
    public function save(CollectTask $collectTask, bool $flush = true): void;

    public function get(string $id): CollectTask;

    /**
     * @return array<CollectTask>
     */
    public function findActiveBySourceId(string $sourceId): array;

    /**
     * @return array<CollectTask>
     */
    public function findActiveTasksByWatchFileId(string $watchFileId): array;

    /**
     * @return array<CollectTask>
     */
    public function findAllByWatchFileId(string $watchFileId): array;
}
