<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

interface StrategicQuestionGatewayInterface
{
    public function save(StrategicQuestion $strategicQuestion): void;

    public function get(string $strategicQuestionId): StrategicQuestion;

    public function findByQuestionAndWatchFile(string $question, string $watchFileId): ?StrategicQuestion;
}
