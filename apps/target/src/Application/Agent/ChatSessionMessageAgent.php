<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\Chat\ModelMessageAction;

class ChatSessionMessageAgent extends TriggerAgent
{
    private const NAME = 'ChatSessionMessage';

    public function __construct(
        array $data,
        ?string $watchFileId = null,
        ?string $userId = null,
        \DateTime $triggeredAt = new \DateTime(),
    ) {
        parent::__construct(
            name: self::NAME,
            data: $data,
            responseType: ModelMessageAction::class,
            watchFileId: $watchFileId,
            userId: $userId,
            triggeredAt: $triggeredAt,
        );
    }
}
