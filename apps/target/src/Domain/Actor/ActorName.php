<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use Webmozart\Assert\Assert;

use function Symfony\Component\String\u;

readonly class ActorName
{
    private function __construct(
        public string $normalized,
        public string $display,
    ) {
    }

    public static function fromString(string $input): self
    {
        Assert::stringNotEmpty(trim($input), 'Actor name must not be empty');

        $display = self::cleanInput($input);
        $normalized = self::normalize($display);

        return new self($normalized, $display);
    }

    private static function cleanInput(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($value));
    }

    private static function normalize(string $value): string
    {
        return u($value)
            ->lower()
            ->ascii()
            ->toString();
    }

    public function equals(self $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    public function __toString(): string
    {
        return $this->display;
    }
}
