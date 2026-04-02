<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\Exception\StrategicQuestionNotFoundException;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class StrategicQuestionDoctrineGateway implements StrategicQuestionGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(StrategicQuestion $strategicQuestion): void
    {
        $this->entityManager->persist($strategicQuestion);
        $this->entityManager->flush();
    }

    public function get(string $strategicQuestionId): StrategicQuestion
    {
        $strategicQuestion = $this->entityManager->find(StrategicQuestion::class, $strategicQuestionId);

        if (null === $strategicQuestion) {
            throw new StrategicQuestionNotFoundException(\sprintf(
                'Strategic question with ID "%s" not found.',
                $strategicQuestionId
            ), );
        }

        return $strategicQuestion;
    }

    public function findByQuestionAndWatchFile(string $question, string $watchFileId): ?StrategicQuestion
    {
        $strategicQuestionId = $this->entityManager
            ->getConnection()
            ->executeQuery(
                'SELECT id FROM strategic_question WHERE question->>\'en\' = :question AND watch_file_id = :watchFileId LIMIT 1',
                [
                    'question' => $question,
                    'watchFileId' => $watchFileId,
                ]
            )
            ->fetchOne();

        if (empty($strategicQuestionId) || !\is_string($strategicQuestionId) || !Uuid::isValid($strategicQuestionId)) {
            return null;
        }

        return $this->entityManager->find(StrategicQuestion::class, $strategicQuestionId);
    }
}
