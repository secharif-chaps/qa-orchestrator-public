<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Collect;

use App\Domain\Collect\CollectTaskResult;
use PHPUnit\Framework\TestCase;

class CollectTaskResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $data = [
            'items' => [1, 2, 3],
            'count' => 3,
        ];
        $metadata = [
            'execution_time' => 1.5,
        ];

        $result = CollectTaskResult::success($data, $metadata);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertEquals($data, $result->getData());
        $this->assertNull($result->getErrorMessage());
        $this->assertEquals($metadata, $result->getMetadata());
    }

    public function testSuccessResultWithDefaults(): void
    {
        $result = CollectTaskResult::success();

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertEquals([], $result->getData());
        $this->assertNull($result->getErrorMessage());
        $this->assertEquals([], $result->getMetadata());
    }

    public function testFailureResult(): void
    {
        $errorMessage = 'Connection timeout';
        $metadata = [
            'retry_count' => 3,
            'last_error' => 'Network error',
        ];

        $result = CollectTaskResult::failure($errorMessage, $metadata);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertNull($result->getData());
        $this->assertEquals($errorMessage, $result->getErrorMessage());
        $this->assertEquals($metadata, $result->getMetadata());
    }

    public function testFailureResultWithDefaults(): void
    {
        $errorMessage = 'Something went wrong';

        $result = CollectTaskResult::failure($errorMessage);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertNull($result->getData());
        $this->assertEquals($errorMessage, $result->getErrorMessage());
        $this->assertEquals([], $result->getMetadata());
    }

    public function testToArray(): void
    {
        $data = [
            'result' => 'success',
        ];
        $metadata = [
            'duration' => 2.3,
        ];

        $successResult = CollectTaskResult::success($data, $metadata);
        $expectedSuccess = [
            'success' => true,
            'data' => $data,
            'error_message' => null,
            'metadata' => $metadata,
        ];

        $this->assertEquals($expectedSuccess, $successResult->toArray());

        $errorMessage = 'Failed to process';
        $failureResult = CollectTaskResult::failure($errorMessage, $metadata);
        $expectedFailure = [
            'success' => false,
            'data' => null,
            'error_message' => $errorMessage,
            'metadata' => $metadata,
        ];

        $this->assertEquals($expectedFailure, $failureResult->toArray());
    }
}
