<?php

declare(strict_types=1);

namespace App\Domain\AI;

use App\Domain\Shared\TranslatedText;

/**
 * Sanitizes output from LLM responses.
 *
 * LLMs often return text with markdown formatting artifacts
 * like backticks, code blocks, or other formatting characters
 * that should be stripped for plain text usage.
 */
interface LlmOutputSanitizerInterface
{
    /**
     * Sanitizes LLM output string by removing formatting artifacts.
     */
    public function sanitize(string $input): string;

    /**
     * Sanitizes a TranslatedText by removing formatting artifacts from both languages.
     */
    public function sanitizeTranslatedText(TranslatedText $input): TranslatedText;
}
