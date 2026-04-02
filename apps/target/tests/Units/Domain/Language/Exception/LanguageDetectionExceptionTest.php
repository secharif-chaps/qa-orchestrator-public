<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Language\Exception;

use App\Domain\Language\Exception\LanguageDetectionException;
use PHPUnit\Framework\TestCase;

class LanguageDetectionExceptionTest extends TestCase
{
    public function testApiRequestFailedPreservesOriginalException(): void
    {
        $original = new \RuntimeException('Network error');
        $exception = LanguageDetectionException::apiRequestFailed('test', $original);

        $this->assertSame($original, $exception->getPrevious());
        $this->assertStringContainsString('Language detection API request failed: test', $exception->getMessage());
    }

    public function testApiRequestFailedWithoutPreviousException(): void
    {
        $exception = LanguageDetectionException::apiRequestFailed('timeout occurred');

        $this->assertNull($exception->getPrevious());
        $this->assertStringContainsString('timeout occurred', $exception->getMessage());
    }

    public function testHttpErrorFormatsMessageCorrectly(): void
    {
        $exception = LanguageDetectionException::httpError(404, 'Not Found');

        $this->assertStringContainsString('status 404', $exception->getMessage());
        $this->assertStringContainsString('Not Found', $exception->getMessage());
    }

    public function testHttpErrorTruncatesLongResponse(): void
    {
        $longResponse = str_repeat('a', 1000);
        $exception = LanguageDetectionException::httpError(500, $longResponse);

        // Should be truncated to ~200 chars + '...'
        $this->assertLessThan(300, \strlen($exception->getMessage()));
        $this->assertStringContainsString('...', $exception->getMessage());
    }

    public function testHttpErrorDoesNotTruncateShortResponse(): void
    {
        $shortResponse = 'Bad Request';
        $exception = LanguageDetectionException::httpError(400, $shortResponse);

        $this->assertStringContainsString('Bad Request', $exception->getMessage());
        $this->assertStringNotContainsString('...', $exception->getMessage());
    }

    public function testInvalidResponseFormatMessage(): void
    {
        $exception = LanguageDetectionException::invalidResponseFormat('missing language field');

        $this->assertStringContainsString('Invalid language detection response format', $exception->getMessage());
        $this->assertStringContainsString('missing language field', $exception->getMessage());
    }

    public function testInvalidJsonResponseMessage(): void
    {
        $exception = LanguageDetectionException::invalidJsonResponse('invalid JSON syntax');

        $this->assertStringContainsString('Failed to parse language detection response', $exception->getMessage());
        $this->assertStringContainsString('invalid JSON syntax', $exception->getMessage());
    }
}
