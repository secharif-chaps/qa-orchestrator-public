<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\WatchFile\Workflow\ProcessWatchFileClassificationResultAction;

class ClassifyWatchFileTypeAgent extends TriggerAgent
{
    private const NAME = 'ClassifyWatchFileType';

    public function __construct(
        array $data,
        ?string $watchFileId = null,
        ?string $userId = null,
        \DateTime $triggeredAt = new \DateTime(),
    ) {
        parent::__construct(
            self::NAME,
            $data,
            ProcessWatchFileClassificationResultAction::class,
            $watchFileId,
            $userId,
            $triggeredAt,
        );
    }
}
