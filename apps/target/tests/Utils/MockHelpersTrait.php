<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Use createMockWithExpectations() when the test configures expectations (->expects()).
 * Use createStub() when the double is only used for stubbing return values (->method()->willReturn()).
 */
trait MockHelpersTrait
{
    /**
     * Creates a mock object for the specified class. Use this only when the test
     * configures expectations (->expects()). Otherwise use createStub() to avoid PHPUnit 12 notices.
     *
     * @template T of object
     *
     * @param class-string<T> $originalClassName
     *
     * @return T&MockObject
     */
    protected function createMockWithExpectations(string $originalClassName): MockObject
    {
        /** @var T&MockObject $mock */
        $mock = parent::createMock($originalClassName);

        return $mock;
    }
}
