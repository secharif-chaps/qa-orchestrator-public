<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Document\HtmlMetadata;

trait ApifyNormalizerTrait
{
    private const int EXCERPT_MIN_LENGTH = 10;

    /**
     * @param array<string, mixed> $item
     * @param list<string>         $keys
     */
    private function buildTitle(array $item, array $keys): string
    {
        $title = $this->extractString($item, $keys) ?? HtmlMetadata::UNTITLED;
        $title = mb_substr($title, 0, 500);

        return '' !== $title ? $title : HtmlMetadata::UNTITLED;
    }

    /**
     * Build a valid excerpt from candidate strings, longest-first.
     * Returns null if no candidate reaches the minimum length — the normalizer should skip the item.
     */
    private function buildExcerpt(string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $excerpt = mb_substr(strip_tags($candidate), 0, 1000);
            if (mb_strlen($excerpt) >= self::EXCERPT_MIN_LENGTH) {
                return $excerpt;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string>         $keys
     */
    private function buildDate(array $item, array $keys): ?\DateTimeImmutable
    {
        foreach ($keys as $key) {
            $date = $this->parseDate($item[$key] ?? null);
            if (null !== $date) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string>         $keys
     */
    private function extractString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && \is_string($item[$key]) && '' !== $item[$key]) {
                return $item[$key];
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
