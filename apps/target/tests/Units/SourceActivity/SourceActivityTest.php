<?php

declare(strict_types=1);

namespace App\Tests\Units\SourceActivity;

use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\TestCase;

class SourceActivityTest extends TestCase
{
    private Source $source;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->user = new User(
            id: 'user-123',
            email: 'test@example.com',
            roles: ['ROLE_USER'],
            userName: 'testuser'
        );

        $this->watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Test objective', organisation: new Organisation('Test Org', 'test-org-id'),
            createdBy: $this->user
        );

        $this->source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinent FR', 'Relevant EN'),
            actor: null,
            watchFile: $this->watchFile
        );
    }

    public function testSourceActivityCreation(): void
    {
        $actionType = SourceActivityActionType::SOURCE_CONNECTED;
        $actionData = [
            'connection_data' => [
                'ip' => '127.0.0.1',
            ],
            'timestamp' => '2024-01-01T12:00:00Z',
        ];

        $sourceActivity = new SourceActivity($this->source, $this->user, $actionType, $actionData);

        $this->assertNotNull($sourceActivity->getId());
        $this->assertEquals($this->source, $sourceActivity->getSource());
        $this->assertEquals($this->user, $sourceActivity->getUser());
        $this->assertEquals($actionType, $sourceActivity->getActionType());
        $this->assertEquals($actionData, $sourceActivity->getActionData());
        $this->assertInstanceOf(\DateTime::class, $sourceActivity->getCreatedAt());
        $this->assertEquals($this->watchFile, $sourceActivity->getWatchFile());
    }

    public function testSourceActivityWithAllActionTypes(): void
    {
        $allActionTypes = SourceActivityActionType::cases();

        foreach ($allActionTypes as $actionType) {
            $sourceActivity = new SourceActivity(
                $this->source,
                $this->user,
                $actionType,
                [
                    'test' => 'data',
                ]
            );

            $this->assertEquals($actionType, $sourceActivity->getActionType());
        }
    }

    public function testSourceActivityActionDataStorage(): void
    {
        $complexActionData = [
            'connection_info' => [
                'ip' => '192.168.1.1',
                'port' => 443,
                'protocol' => 'https',
            ],
            'performance' => [
                'response_time' => 150,
                'bytes_transferred' => 2048,
            ],
            'context' => [
                'user_agent' => 'TestAgent/1.0',
                'referer' => 'https://test.com',
            ],
        ];

        $sourceActivity = new SourceActivity(
            $this->source,
            $this->user,
            SourceActivityActionType::SOURCE_DATA_RETRIEVED,
            $complexActionData
        );

        $this->assertEquals($complexActionData, $sourceActivity->getActionData());
    }
}
