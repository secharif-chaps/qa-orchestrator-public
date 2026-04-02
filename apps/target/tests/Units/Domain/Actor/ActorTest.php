<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Actor;

use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use PHPUnit\Framework\TestCase;

class ActorTest extends TestCase
{
    public function testActorProperties(): void
    {
        $actor = new Actor('John Doe', new Organisation('Test Org', 'test-org-id'));
        $this->assertEquals('John Doe', $actor->getLabel());
        $this->assertNull($actor->getPrimaryDomain());
        $actor->setPrimaryDomain('example.com');
        $this->assertEquals('example.com', $actor->getPrimaryDomain());
    }

    public function testSetPrimaryDomainRejectsUrl(): void
    {
        $actor = new Actor('Jane Doe', new Organisation('Test Org', 'test-org-id'));
        $this->expectException(\InvalidArgumentException::class);
        $actor->setPrimaryDomain('https://example.com');
    }

    public function testSetAndGetLabel(): void
    {
        $actor = new Actor('Initial', new Organisation('Test Org', 'test-org-id'));
        $actor->setLabel('Changed');
        $this->assertEquals('Changed', $actor->getLabel());
    }
}
