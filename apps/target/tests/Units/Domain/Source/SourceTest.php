<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Source;

use App\Domain\Actor\Actor;
use App\Domain\Chat\Message;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\TestCase;

class SourceTest extends TestCase
{
    public function testSourcePropertiesAndActivation(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile,
            'query',
            [
                'param' => 'value',
            ]
        );
        $this->assertEquals('SourceName', $source->getName());
        $description = $source->getDescription();
        $this->assertInstanceOf(TranslatedText::class, $description);
        $this->assertEquals('desc fr', $description->fr);
        $this->assertEquals('desc en', $description->en);
        $this->assertEquals(SourceType::WEBSITE, $source->getType());
        $this->assertEquals('https://example.com', $source->getUrl());
        $this->assertEquals('example.com', $source->getPrimaryDomain());
        $this->assertEquals('query', $source->getQuery());
        $relevance = $source->getRelevance();
        $this->assertInstanceOf(TranslatedText::class, $relevance);
        $this->assertEquals('rel fr', $relevance->fr);
        $this->assertEquals('rel en', $relevance->en);
        $this->assertEquals([
            'param' => 'value',
        ], $source->getParameters());
        $this->assertSame($watchFile, $source->getWatchFile());
        $this->assertTrue($source->isActive());
        $source->deactivate();
        $this->assertFalse($source->isActive());
        $source->activate();
        $this->assertTrue($source->isActive());
    }

    public function testSetAddedByMessage(): void
    {
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor
        );
        $message = new Message();
        $source->setAddedByMessage($message);
        $this->assertSame($message, $source->getAddedByMessage());
    }

    public function testUpdateQueryAndParameters(): void
    {
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor
        );
        $source->updateQuery('new query');
        $this->assertEquals('new query', $source->getQuery());
        $source->updateParameters([
            'foo' => 'bar',
        ]);
        $this->assertEquals([
            'foo' => 'bar',
        ], $source->getParameters());
    }

    public function testUpdateCollectStatusWithCreatedStatus(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile
        );

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::CREATED);
        $source->updateCollectStatus($task);

        $this->assertEquals(CollectStatus::RUNNING, $source->getCollectStatus());
    }

    public function testUpdateCollectStatusWithQueuedStatus(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile
        );

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::QUEUED);
        $source->updateCollectStatus($task);

        $this->assertEquals(CollectStatus::RUNNING, $source->getCollectStatus());
    }

    public function testUpdateCollectStatusWithRunningStatus(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile
        );

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::RUNNING);
        $source->updateCollectStatus($task);

        $this->assertEquals(CollectStatus::RUNNING, $source->getCollectStatus());
    }

    public function testUpdateCollectStatusWithCompletedStatus(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile
        );

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::COMPLETED);
        $source->updateCollectStatus($task);

        $this->assertEquals(CollectStatus::STOPPED, $source->getCollectStatus());
    }

    public function testUpdateCollectStatusWithCancelledStatus(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile
        );

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::CANCELLED);
        $source->updateCollectStatus($task);

        $this->assertEquals(CollectStatus::STOPPED, $source->getCollectStatus());
    }

    public function testUpdateCollectStatusWithFailedStatus(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            new TranslatedText('desc fr', 'desc en'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText('rel fr', 'rel en'),
            $actor,
            $watchFile
        );

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::FAILED);
        $source->updateCollectStatus($task);

        $this->assertEquals(CollectStatus::ERROR, $source->getCollectStatus());
    }
}
