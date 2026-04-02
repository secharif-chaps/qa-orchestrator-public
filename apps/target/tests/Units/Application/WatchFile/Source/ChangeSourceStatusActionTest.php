<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Application\WatchFile\Source\ChangeSourceStatusAction;
use App\Domain\Source\SourceStatus;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class ChangeSourceStatusActionTest extends TestCase
{
    public function testChangeSourceStatusAction(): void
    {
        $user = new User('user-id', 'user@example.com');
        $action = new ChangeSourceStatusAction(
            watchFileId: 'watchfile-id',
            sourceId: 'source-id',
            status: SourceStatus::ACTIVE,
            user: $user
        );

        $this->assertEquals('watchfile-id', $action->watchFileId);
        $this->assertEquals('source-id', $action->sourceId);
        $this->assertEquals(SourceStatus::ACTIVE, $action->status);
        $this->assertSame($user, $action->user);
    }

    public function testChangeSourceStatusActionWithDisabledStatus(): void
    {
        $user = new User('user-id', 'user@example.com');
        $action = new ChangeSourceStatusAction(
            watchFileId: 'watchfile-id',
            sourceId: 'source-id',
            status: SourceStatus::INACTIVE,
            user: $user
        );

        $this->assertEquals('watchfile-id', $action->watchFileId);
        $this->assertEquals('source-id', $action->sourceId);
        $this->assertEquals(SourceStatus::INACTIVE, $action->status);
        $this->assertSame($user, $action->user);
    }

    public function testChangeSourceStatusActionWithNullWatchFileId(): void
    {
        $user = new User('user-id', 'user@example.com');
        $action = new ChangeSourceStatusAction(
            watchFileId: null,
            sourceId: 'source-id',
            status: SourceStatus::ACTIVE,
            user: $user
        );

        $this->assertNull($action->watchFileId);
        $this->assertEquals('source-id', $action->sourceId);
        $this->assertEquals(SourceStatus::ACTIVE, $action->status);
        $this->assertSame($user, $action->user);
    }

    public function testChangeSourceStatusActionWithNullUser(): void
    {
        $action = new ChangeSourceStatusAction(
            watchFileId: 'watchfile-id',
            sourceId: 'source-id',
            status: SourceStatus::ACTIVE,
            user: null
        );

        $this->assertEquals('watchfile-id', $action->watchFileId);
        $this->assertEquals('source-id', $action->sourceId);
        $this->assertEquals(SourceStatus::ACTIVE, $action->status);
        $this->assertNull($action->user);
    }
}
