<?php

declare(strict_types=1);

namespace App\Domain\Language\Exception;

use App\Domain\Shared\DomainException;

/**
 * Exception thrown when language detection fails.
 */
class LanguageDetectionException extends DomainException
{
    public static function apiRequestFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Language detection API request failed: %s', $reason), 0, $previous);
    }

    public static function invalidResponseFormat(string $reason): self
    {
        return new self(\sprintf('Invalid language detection response format: %s', $reason));
    }

    public static function invalidJsonResponse(string $reason): self
    {
        return new self(\sprintf('Failed to parse language detection response: %s', $reason));
    }

    public static function httpError(int $statusCode, string $response): self
    {
        $truncatedResponse = \strlen($response) > 200
            ? substr($response, 0, 200) . '...'
            : $response;

        return new self(
            \sprintf('Language detection API request failed with status %d: %s', $statusCode, $truncatedResponse)
        );
    }
}
