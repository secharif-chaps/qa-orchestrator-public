<?php

declare(strict_types=1);

namespace App\Domain\Chat\Content;

interface MessageContentGatewayInterface
{
    public function get(string $messageId): MessageContent;

    public function save(MessageContent $messageContent): void;
}
