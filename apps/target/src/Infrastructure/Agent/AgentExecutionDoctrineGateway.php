<?php

declare(strict_types=1);

namespace App\Infrastructure\Agent;

use App\Domain\Agent\AgentExecution;
use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\Agent\AgentExecutionStatus;
use Doctrine\ORM\EntityManagerInterface;

class AgentExecutionDoctrineGateway implements AgentExecutionGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(AgentExecution $execution): void
    {
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }

    public function findByExecutionId(string $executionId): ?AgentExecution
    {
        return $this->entityManager
            ->getRepository(AgentExecution::class)
            ->findOneBy([
                'executionId' => $executionId,
            ]);
    }

    public function findStaleExecutions(int $timeoutSeconds): array
    {
        $threshold = new \DateTimeImmutable(\sprintf('-%d seconds', $timeoutSeconds));

        /** @var list<AgentExecution> $results */
        $results = $this->entityManager
            ->getRepository(AgentExecution::class)
            ->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.startedAt < :threshold')
            ->setParameter('status', AgentExecutionStatus::Running->value)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        return $results;
    }
}
