<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Domain\AI\LlmOutputSanitizerInterface;
use App\Domain\Shared\TranslatedText;

/**
 * Sanitizes output from LLM responses by removing common formatting artifacts.
 */
final readonly class LlmOutputSanitizer implements LlmOutputSanitizerInterface
{
    public function sanitize(string $input): string
    {
        $sanitized = trim($input);

        // Remove backticks wrapping the entire string (e.g., `text` or ```text```)
        $sanitized = preg_replace('/^`+(.+?)`+$/s', '$1', $sanitized) ?? $sanitized;

        return trim($sanitized);
    }

    public function sanitizeTranslatedText(TranslatedText $input): TranslatedText
    {
        return new TranslatedText($this->sanitize($input->fr), $this->sanitize($input->en));
    }
}
