<?php

declare(strict_types=1);

namespace App\Tests\Units\SourceActivity;

use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Event\SourceAddedToWatchFileEvent;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\Source\SourceType;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\SourceActivity\SourceActivityEventListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SourceActivityEventListenerTest extends TestCase
{
    private SourceActivityEventListener $listener;
    private SourceActivityLoggerInterface&MockObject $logger;
    private SourceActivityGatewayInterface&MockObject $gateway;
    private Source $source;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(SourceActivityLoggerInterface::class);
        $this->gateway = $this->createMock(SourceActivityGatewayInterface::class);

        $this->listener = new SourceActivityEventListener($this->logger, $this->gateway);

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

    public function testOnSourceStatusChanged(): void
    {
        $oldStatus = SourceStatus::INACTIVE;
        $newStatus = SourceStatus::ACTIVE;

        $event = new SourceStatusChangedEvent(
            $this->source,
            $this->watchFile,
            $this->user,
            $newStatus,
            $oldStatus
        );

        $expectedSourceActivity = $this->createStub(SourceActivity::class);

        $this->logger->expects($this->once())
            ->method('logSourceStatusChanged')
            ->with(
                $this->source,
                $this->user,
                $oldStatus,
                $newStatus,
                $this->callback(function ($context) {
                    return isset($context['watch_file_name'])
                           && isset($context['automatic_trigger'])
                           && true === $context['automatic_trigger'];
                })
            )
            ->willReturn($expectedSourceActivity);

        $this->gateway->expects($this->once())
            ->method('save')
            ->with($expectedSourceActivity);

        $this->listener->onSourceStatusChanged($event);
    }

    public function testOnSourceAddedToWatchFile(): void
    {
        $event = new SourceAddedToWatchFileEvent($this->source, $this->watchFile, $this->user);

        $expectedSourceActivity = $this->createStub(SourceActivity::class);

        $this->logger->expects($this->once())
            ->method('logSourceAddedToWatchFile')
            ->with(
                $this->source,
                $this->user,
                $this->callback(function ($context) {
                    return isset($context['watch_file_name'])
                           && isset($context['automatic_trigger'])
                           && true === $context['automatic_trigger'];
                })
            )
            ->willReturn($expectedSourceActivity);

        $this->gateway->expects($this->once())
            ->method('save')
            ->with($expectedSourceActivity);

        $this->listener->onSourceAddedToWatchFile($event);
    }
}
