<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat\Content;

use App\Domain\Chat\Content\MessageContent;
use App\Domain\Chat\Content\MessageContentGatewayInterface;
use App\Domain\Chat\Content\MessageContentNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class MessageContentDoctrineGateway implements MessageContentGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $messageId): MessageContent
    {
        $conversation = $this->entityManager->getRepository(MessageContent::class)->find($messageId);

        if (null === $conversation) {
            throw new MessageContentNotFoundException(\sprintf('MessageContent not found for ID %d', $messageId));
        }

        return $conversation;
    }

    public function save(MessageContent $messageContent): void
    {
        $this->entityManager->persist($messageContent);
        $this->entityManager->flush();
    }
}
