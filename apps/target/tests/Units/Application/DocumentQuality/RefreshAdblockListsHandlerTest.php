<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\DocumentQuality;

use App\Application\DocumentQuality\RefreshAdblockListsAction;
use App\Application\DocumentQuality\RefreshAdblockListsHandler;
use App\Tests\Units\Infrastructure\DocumentQuality\Stub\InMemoryAdblockDomainListProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshAdblockListsHandler::class)]
class RefreshAdblockListsHandlerTest extends TestCase
{
    public function testInvokeDelegatesToProviderAndReturnsCount(): void
    {
        $provider = new InMemoryAdblockDomainListProvider()
            ->addMatch('one.example')
            ->addMatch('two.example');

        $handler = new RefreshAdblockListsHandler($provider);

        $count = $handler(new RefreshAdblockListsAction());

        self::assertSame(2, $count);
        self::assertSame(1, $provider->refreshCallCount);
    }
}
