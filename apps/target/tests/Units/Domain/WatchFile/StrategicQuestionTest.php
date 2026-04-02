<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\WatchFile;

use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\TestCase;

class StrategicQuestionTest extends TestCase
{
    public function testStrategicQuestionProperties(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $question = new StrategicQuestion(
            question: new TranslatedText('qfr', 'qen'),
            context: new TranslatedText('cfr', 'cen'),
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
            watchFile: $watchFile,
        );
        $questionText = $question->getQuestion();
        $this->assertEquals('qfr', $questionText->fr);
        $this->assertEquals('qen', $questionText->en);
        $contextText = $question->getContext();
        $this->assertEquals('cfr', $contextText->fr);
        $this->assertEquals('cen', $contextText->en);
        $this->assertSame($watchFile, $question->getWatchFile());
        $this->assertEquals(MonitoringType::COMPETITIVE, $question->getMonitoringDimension());
        $this->assertEquals(1, $question->getPriority());
        $this->assertEquals('analysis', $question->getExpectedOutputType());
    }

    public function testAddAndRemoveSearchQuery(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $question = new StrategicQuestion(
            question: new TranslatedText('qfr', 'qen'),
            context: new TranslatedText('cfr', 'cen'),
            monitoringDimension: MonitoringType::STRATEGIC,
            priority: 2,
            expectedOutputType: 'report',
            watchFile: $watchFile,
        );
        $searchQuery = new SearchQuery('term', 'FR', 'fr', 'general', 'Test rationale', $question);
        $question->addSearchQuery($searchQuery);
        $this->assertCount(1, $question->getSearchQueries());
        $question->removeSearchQuery($searchQuery);
        $this->assertCount(0, $question->getSearchQueries());
    }

    public function testSettersAndMarkMethods(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $question = new StrategicQuestion(
            question: new TranslatedText('qfr', 'qen'),
            context: new TranslatedText('cfr', 'cen'),
            monitoringDimension: MonitoringType::TECHNOLOGICAL,
            priority: 3,
            expectedOutputType: 'summary',
            watchFile: $watchFile,
        );
        $question->markSearchQueriesAsGenerated();
        $this->assertNotNull($question->getSearchQueriesGeneratedAt());
        $question->markSearchQueriesAsExecuted();
        $this->assertNotNull($question->getSearchQueriesExecutedAt());
    }
}
