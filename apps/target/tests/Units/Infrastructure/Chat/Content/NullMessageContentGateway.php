<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Chat\Content;

use App\Domain\Chat\Content\MessageContent;
use App\Domain\Chat\Content\MessageContentGatewayInterface;
use App\Domain\Chat\Content\MessageContentNotFoundException;

class NullMessageContentGateway implements MessageContentGatewayInterface
{
    /**
     * @var array<string, MessageContent>
     */
    private array $messageContents = [];

    public function get(string $messageId): MessageContent
    {
        if (!isset($this->messageContents[$messageId])) {
            throw new MessageContentNotFoundException(\sprintf('Message content with id %s not found', $messageId));
        }

        return $this->messageContents[$messageId];
    }

    public function save(MessageContent $messageContent): void
    {
        $this->messageContents[$messageContent->getId()] = $messageContent;
    }
}
