<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect;

use App\Application\Collect\PushStreamCollectTaskDataAction;
use App\Application\Collect\PushStreamCollectTaskDataHandler;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Tests\Units\Infrastructure\Collect\Stream\NullPushStreamServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushStreamCollectTaskDataAction::class)]
#[CoversClass(PushStreamCollectTaskDataHandler::class)]
class PushStreamCollectTaskDataHandlerTest extends TestCase
{
    private PushStreamCollectTaskDataHandler $handler;
    private NullPushStreamServer $pushStreamServer;

    protected function setUp(): void
    {
        $this->pushStreamServer = new NullPushStreamServer();
        $this->handler = new PushStreamCollectTaskDataHandler($this->pushStreamServer);
    }

    public function testListenSuccessfullyReturnsTrue(): void
    {
        $action = new PushStreamCollectTaskDataAction();

        $result = ($this->handler)($action);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->pushStreamServer->getListenCallCount());
        $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
        $this->assertFalse($this->pushStreamServer->isListening());
    }

    public function testListenSuccessfullyReturnsFalse(): void
    {
        $this->pushStreamServer->setReturnFalse();

        $action = new PushStreamCollectTaskDataAction();

        $result = ($this->handler)($action);

        $this->assertFalse($result);
        $this->assertEquals(1, $this->pushStreamServer->getListenCallCount());
        $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
        $this->assertFalse($this->pushStreamServer->isListening());
    }

    public function testListenThrowsCollectDataStreamException(): void
    {
        $this->pushStreamServer->throwExceptionOnListen('WebSocket server error');

        $action = new PushStreamCollectTaskDataAction();

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('WebSocket server error');

        try {
            ($this->handler)($action);
        } finally {
            // Ensure stop is always called
            $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
        }
    }

    public function testListenWrapsGenericExceptionIntoCollectDataStreamException(): void
    {
        $this->pushStreamServer->throwGenericExceptionOnListen('Generic runtime error');

        $action = new PushStreamCollectTaskDataAction();

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Generic runtime error');

        try {
            ($this->handler)($action);
        } finally {
            // Ensure stop is always called
            $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
        }
    }

    public function testStopIsAlwaysCalledInFinallyBlock(): void
    {
        $this->pushStreamServer->throwExceptionOnListen('Test exception');

        $action = new PushStreamCollectTaskDataAction();

        try {
            ($this->handler)($action);
            $this->fail('Expected CollectDataStreamException was not thrown');
        } catch (CollectDataStreamException $e) {
            // Expected exception
            $this->assertEquals('Test exception', $e->getMessage());
        }

        // Verify stop was called even though exception was thrown
        $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
        $this->assertFalse($this->pushStreamServer->isListening());
    }

    public function testStopIsCalledAfterSuccessfulListen(): void
    {
        $action = new PushStreamCollectTaskDataAction();

        ($this->handler)($action);

        // Verify stop was called and server is no longer listening
        $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
        $this->assertFalse($this->pushStreamServer->isListening());
    }

    public function testExceptionChainingIsPreserved(): void
    {
        $this->pushStreamServer->throwGenericExceptionOnListen('Original error');

        $action = new PushStreamCollectTaskDataAction();

        try {
            ($this->handler)($action);
            $this->fail('Expected CollectDataStreamException was not thrown');
        } catch (CollectDataStreamException $e) {
            // Verify the original exception is preserved
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertEquals('Original error', $e->getPrevious()->getMessage());
        }
    }

    public function testListenReturnsFalseDoesNotThrowException(): void
    {
        $this->pushStreamServer->setReturnFalse();

        $action = new PushStreamCollectTaskDataAction();

        // Should not throw exception, just return false
        $result = ($this->handler)($action);

        $this->assertFalse($result);
        $this->assertEquals(1, $this->pushStreamServer->getStopCallCount());
    }
}
