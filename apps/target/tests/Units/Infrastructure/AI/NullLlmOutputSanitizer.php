<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\AI;

use App\Domain\AI\LlmOutputSanitizerInterface;
use App\Domain\Shared\TranslatedText;

class NullLlmOutputSanitizer implements LlmOutputSanitizerInterface
{
    public function sanitize(string $input): string
    {
        return $input;
    }

    public function sanitizeTranslatedText(TranslatedText $input): TranslatedText
    {
        return $input;
    }
}
