<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class ApifyConfigurationException extends CollectException
{
    public static function missingVariable(string $variable): self
    {
        return new self(\sprintf('Required template variable "%s" is not available', $variable));
    }

    public static function missingRequiredField(string $field): self
    {
        return new self(\sprintf('Required field "%s" not found in actor input', $field));
    }

    public static function invalidTemplate(string $message): self
    {
        return new self(\sprintf('Invalid Apify input template: %s', $message));
    }

    public static function encodingFailed(string $variable): self
    {
        return new self(\sprintf('Failed to JSON-encode value for template variable "%s"', $variable));
    }
}
