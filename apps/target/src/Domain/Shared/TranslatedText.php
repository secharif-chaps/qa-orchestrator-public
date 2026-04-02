<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Symfony\Component\Serializer\Annotation\Groups;
use Webmozart\Assert\Assert;

/**
 * Value object representing text in multiple languages (fr, en).
 * Immutable and self-validating.
 */
readonly class TranslatedText implements \JsonSerializable
{
    public const array LANGUAGES_SUPPORTED = ['fr', 'en'];

    public function __construct(
        #[Groups([
            'watch_file:read',
            'watch_file:llm',
            'watch_file_event:read',
            'source:read',
            'source_group:read',
            'actor:read',
        ])]
        public string $fr,
        #[Groups([
            'watch_file:read',
            'watch_file:llm',
            'watch_file_event:read',
            'source:read',
            'source_group:read',
            'actor:read',
        ])]
        public string $en,
    ) {
        Assert::notEmpty($this->fr, 'French translation cannot be empty');
        Assert::notEmpty($this->en, 'English translation cannot be empty');
    }

    /**
     * Create from array with 'fr' and 'en' keys.
     *
     * @param array{fr?: mixed, en?: mixed} $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['fr']) || !\is_string($data['fr'])) {
            throw new \InvalidArgumentException('Translation array must contain "fr" key with a string value');
        }

        if (!isset($data['en']) || !\is_string($data['en'])) {
            throw new \InvalidArgumentException('Translation array must contain "en" key with a string value');
        }

        return new self($data['fr'], $data['en']);
    }

    /**
     * @return array{fr: string, en: string}
     */
    public function toArray(): array
    {
        return [
            'fr' => $this->fr,
            'en' => $this->en,
        ];
    }

    /**
     * @return array{fr: string, en: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
