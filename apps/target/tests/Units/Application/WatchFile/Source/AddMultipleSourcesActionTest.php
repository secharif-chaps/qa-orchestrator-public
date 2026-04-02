<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Application\WatchFile\Source\AddMultipleSourcesAction;
use App\Application\WatchFile\Source\AddSourceAction;
use App\Domain\Shared\TranslatedText;
use PHPUnit\Framework\TestCase;

class AddMultipleSourcesActionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $sources = [
            new AddSourceAction(
                watchFileId: 'watch_file_1',
                name: 'Source 1',
                type: 'website',
                primaryDomain: 'example1.com',
                url: 'https://example1.com',
                query: 'query 1',
                description: new TranslatedText('Description 1 FR', 'Description 1 EN'),
                relevance: new TranslatedText('Relevance 1 FR', 'Relevance 1 EN'),
            ),
            new AddSourceAction(
                watchFileId: 'watch_file_2',
                name: 'Source 2',
                type: 'rss',
                primaryDomain: 'example2.com',
                url: 'https://example2.com',
                query: 'query 2',
                description: new TranslatedText('Description 2 FR', 'Description 2 EN'),
                relevance: new TranslatedText('Relevance 2 FR', 'Relevance 2 EN'),
            ),
        ];

        $action = new AddMultipleSourcesAction(
            watchFileId: 'common_watch_file',
            sources: $sources,
            messageContentId: 'common_message',
        );

        $this->assertEquals('common_watch_file', $action->watchFileId);
        $this->assertSame($sources, $action->sources);
        $this->assertEquals('common_message', $action->messageContentId);
        $this->assertEquals(2, $action->getSourceCount());
        $this->assertTrue($action->hasSources());
    }

    public function testToIndividualActions(): void
    {
        $sources = [
            new AddSourceAction(
                watchFileId: 'original_1',
                name: 'Source 1',
                type: 'website',
                primaryDomain: 'example1.com',
                url: 'https://example1.com',
                query: 'query 1',
                description: new TranslatedText('Description 1 FR', 'Description 1 EN'),
                relevance: new TranslatedText('Relevance 1 FR', 'Relevance 1 EN'),
                messageId: 'original_message_1',
                actorId: 'original_actor_1',
            ),
            new AddSourceAction(
                watchFileId: 'original_2',
                name: 'Source 2',
                type: 'rss',
                primaryDomain: 'example2.com',
                url: 'https://example2.com',
                query: 'query 2',
                description: new TranslatedText('Description 2 FR', 'Description 2 EN'),
                relevance: new TranslatedText('Relevance 2 FR', 'Relevance 2 EN'),
                messageId: 'original_message_2',
                actorId: 'original_actor_2',
            ),
        ];

        $action = new AddMultipleSourcesAction(
            watchFileId: 'common_watch_file',
            sources: $sources,
            messageContentId: 'common_message',
        );

        $individualActions = $action->toIndividualActions();

        $this->assertCount(2, $individualActions);

        // Check first action
        $firstAction = $individualActions[0];
        $this->assertEquals('common_watch_file', $firstAction->watchFileId);
        $this->assertEquals('Source 1', $firstAction->name);
        $this->assertEquals('website', $firstAction->type);
        $this->assertEquals('common_message', $firstAction->messageId);
        $this->assertEquals('original_actor_1', $firstAction->actorId);

        // Check second action
        $secondAction = $individualActions[1];
        $this->assertEquals('common_watch_file', $secondAction->watchFileId);
        $this->assertEquals('Source 2', $secondAction->name);
        $this->assertEquals('rss', $secondAction->type);
        $this->assertEquals('common_message', $secondAction->messageId);
        $this->assertEquals('original_actor_2', $secondAction->actorId);
    }

    public function testToIndividualActionsWithoutCommonFields(): void
    {
        $sources = [
            new AddSourceAction(
                watchFileId: 'original_1',
                name: 'Source 1',
                type: 'website',
                primaryDomain: 'example1.com',
                url: 'https://example1.com',
                query: 'query 1',
                description: new TranslatedText('Description 1 FR', 'Description 1 EN'),
                relevance: new TranslatedText('Relevance 1 FR', 'Relevance 1 EN'),
                messageId: 'original_message_1',
                actorId: 'original_actor_1',
            ),
        ];

        $action = new AddMultipleSourcesAction(
            watchFileId: 'common_watch_file',
            sources: $sources,
            // No common messageContentId or actorId
        );

        $individualActions = $action->toIndividualActions();

        $this->assertCount(1, $individualActions);

        $firstAction = $individualActions[0];
        $this->assertEquals('common_watch_file', $firstAction->watchFileId);
        $this->assertEquals('original_message_1', $firstAction->messageId);
        $this->assertEquals('original_actor_1', $firstAction->actorId);
    }

    public function testEmptySources(): void
    {
        $action = new AddMultipleSourcesAction(watchFileId: 'common_watch_file', sources: []);

        $this->assertEquals(0, $action->getSourceCount());
        $this->assertFalse($action->hasSources());
        $this->assertEmpty($action->toIndividualActions());
    }
}
