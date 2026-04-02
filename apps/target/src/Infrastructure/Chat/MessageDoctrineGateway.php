<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageNotFoundException;
use App\Domain\Chat\MessageStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

readonly class MessageDoctrineGateway implements MessageGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): Message
    {
        $message = $this->entityManager->find(Message::class, $id);

        if (null === $message) {
            throw new MessageNotFoundException($id);
        }

        return $message;
    }

    public function save(Message $message): void
    {
        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }

    public function findRecentByConversation(
        string $conversationId,
        array $ignoreMessageIds = [],
        int $limit = 20,
    ): array {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('m', 'c')
            ->from(Message::class, 'm')
            ->leftJoin('m.contents', 'c')
            ->where('m.conversation = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (!empty($ignoreMessageIds)) {
            $query
                ->andWhere('m.id NOT IN (:ignoreMessageIds)')
                ->setParameter('ignoreMessageIds', $ignoreMessageIds);
        }

        /** @var list<Message> $messages */
        $messages = $query
            ->getQuery()
            ->getResult();

        // Reverse to get chronological order (oldest first)
        return array_reverse($messages);
    }

    public function applyEagerLoading(QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        /** @var array<string, list<Join>> $existingJoins */
        $existingJoins = $queryBuilder->getDQLPart('join');

        // Check if contents join already exists
        $hasContentsJoin = false;
        $hasCreatedByJoin = false;

        foreach ($existingJoins as $joins) {
            foreach ($joins as $join) {
                if (str_ends_with($join->getJoin(), '.contents')) {
                    $hasContentsJoin = true;
                }
                if (str_ends_with($join->getJoin(), '.createdBy')) {
                    $hasCreatedByJoin = true;
                }
            }
        }

        // Add contents join if not present
        if (!$hasContentsJoin) {
            $queryBuilder->leftJoin($rootAlias . '.contents', 'message_contents');
            $queryBuilder->addSelect('message_contents');
        }

        // Add createdBy join if not present
        if (!$hasCreatedByJoin) {
            $queryBuilder->leftJoin($rootAlias . '.createdBy', 'message_created_by');
            $queryBuilder->addSelect('message_created_by');
        }
    }

    public function findByConversationAndStatus(string $conversationId, MessageStatus $status): array
    {
        /** @var list<Message> $messages */
        $messages = $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->where('IDENTITY(m.conversation) = :conversationId')
            ->andWhere('m.status = :status')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('status', $status)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $messages;
    }
}
