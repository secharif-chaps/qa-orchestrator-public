<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use Psr\Log\AbstractLogger;
use Webmozart\Assert\Assert;

/**
 * Simple test logger for capturing log messages during tests.
 */
class SimpleTestLogger extends AbstractLogger
{
    /**
     * @var array<array{level: string, message: string|\Stringable, context: array<string, mixed>}>
     */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        Assert::string($level);

        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    public function hasInfoRecords(): bool
    {
        foreach ($this->records as $record) {
            if ('info' === $record['level']) {
                return true;
            }
        }

        return false;
    }

    public function hasErrorRecords(): bool
    {
        foreach ($this->records as $record) {
            if ('error' === $record['level']) {
                return true;
            }
        }

        return false;
    }

    public function getErrorCount(): int
    {
        $count = 0;
        foreach ($this->records as $record) {
            if ('error' === $record['level']) {
                ++$count;
            }
        }

        return $count;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
