<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\WatchFile\Exception\StrategicQuestionNotFoundException;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullStrategicQuestionGateway implements StrategicQuestionGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, StrategicQuestion>
     */
    private array $strategicQuestions = [];

    public function save(StrategicQuestion $strategicQuestion): void
    {
        if (null === $strategicQuestion->getId()) {
            $this->forcePropertyValue($strategicQuestion, Uuid::v4());
        }

        $this->strategicQuestions[$strategicQuestion->getId()] = $strategicQuestion;
    }

    public function get(string $strategicQuestionId): StrategicQuestion
    {
        if (!isset($this->strategicQuestions[$strategicQuestionId])) {
            throw new StrategicQuestionNotFoundException(\sprintf(
                'Strategic Question with id %s not found',
                $strategicQuestionId
            ));
        }

        return $this->strategicQuestions[$strategicQuestionId];
    }

    public function findByQuestionAndWatchFile(string $question, string $watchFileId): ?StrategicQuestion
    {
        foreach ($this->strategicQuestions as $strategicQuestion) {
            if ($strategicQuestion->getQuestion()->en === $question
                && $strategicQuestion->getWatchFile()
->getId() === $watchFileId) {
                return $strategicQuestion;
            }
        }

        return null;
    }
}
