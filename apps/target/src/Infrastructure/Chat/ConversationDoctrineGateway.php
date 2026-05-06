<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use App\Domain\Agent\AgentExecution;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class ConversationDoctrineGateway implements ConversationGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): Conversation
    {
        $conversation = $this->entityManager
            ->getRepository(Conversation::class)
            ->createQueryBuilder('c')
            ->addSelect('m')
            ->addSelect('w')
            ->leftJoin('c.messages', 'm')
            ->leftJoin('c.watchFile', 'w')
            ->andWhere('c.id = :conversationId')
            ->setParameter(':conversationId', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$conversation instanceof Conversation) {
            throw new ConversationNotFoundException(\sprintf('Conversation not found for ID %d', $id));
        }

        return $conversation;
    }

    public function getLastConversationByWatchFileId(string $watchFileId): Conversation
    {
        $conversation = $this->entityManager
            ->getRepository(Conversation::class)
            ->createQueryBuilder('c')
            ->andWhere('c.watchFile = :watchFileId')
            ->setParameter(':watchFileId', $watchFileId)
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $conversation) {
            throw new ConversationNotFoundException(\sprintf(
                'Conversation not found for watchfile ID %d',
                $watchFileId
            ));
        }

        return $conversation;
    }

    public function findByAgentExecution(AgentExecution $execution): ?Conversation
    {
        /** @var Conversation|null */
        return $this->entityManager
            ->getRepository(Conversation::class)
            ->createQueryBuilder('c')
            ->addSelect('m', 'w')
            ->leftJoin('c.messages', 'm')
            ->leftJoin('c.watchFile', 'w')
            ->andWhere('c.agentExecution = :execution')
            ->setParameter('execution', $execution)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function save(Conversation $conversation): void
    {
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();
    }

    public function getLastConversationIdsForWatchFiles(array $watchFileIds): array
    {
        if ([] === $watchFileIds) {
            return [];
        }

        $connection = $this->entityManager->getConnection();

        $placeholders = [];
        $params = [];
        foreach ($watchFileIds as $index => $watchFileId) {
            $placeholders[] = ':id' . $index;
            $params['id' . $index] = $watchFileId;
        }

        $sql = \sprintf(
            'SELECT DISTINCT ON (watch_file_id) id FROM conversation WHERE watch_file_id IN (%s) ORDER BY watch_file_id, created_at DESC',
            implode(', ', $placeholders),
        );

        $result = $connection->executeQuery($sql, $params);

        /** @var string[] $ids */
        $ids = $result->fetchFirstColumn();

        return $ids;
    }
}
