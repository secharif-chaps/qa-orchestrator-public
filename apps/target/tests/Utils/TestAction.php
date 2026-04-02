<?php

declare(strict_types=1);

namespace App\Tests\Utils;

/**
 * Simple test action for webhook testing purposes.
 * This action doesn't require any handlers and is only used for testing the webhook dispatch mechanism.
 */
final readonly class TestAction
{
    public function __construct(
        public mixed $data,
        public string $testId,
    ) {
    }
}
