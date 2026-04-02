<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Type;

use App\Domain\Shared\TranslatedText;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use Webmozart\Assert\Assert;

/**
 * Doctrine custom type for TranslatedText value object.
 * Stores the translation as JSON in the database.
 */
final class TranslatedTextType extends JsonType
{
    /**
     * Convert database JSON to TranslatedText value object.
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?TranslatedText
    {
        if (null === $value || '' === $value) {
            return null;
        }

        // If already a TranslatedText instance, return as-is
        if ($value instanceof TranslatedText) {
            return $value;
        }

        // Decode JSON to array
        $data = parent::convertToPHPValue($value, $platform);
        Assert::isArray($data, 'TranslatedText must be stored as JSON array');
        foreach (TranslatedText::LANGUAGES_SUPPORTED as $language) {
            Assert::keyExists($data, $language, \sprintf('TranslatedText array must have "%s" key', $language));
            Assert::string($data[$language], \sprintf('TranslatedText "%s" value must be a string', $language));
        }

        /** @var array{fr: string, en: string} $data */
        return TranslatedText::fromArray($data);
    }

    /**
     * Convert TranslatedText value object to database JSON.
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof TranslatedText) {
            throw new \InvalidArgumentException(\sprintf(
                'Value must be an instance of %s, %s given',
                TranslatedText::class,
                get_debug_type($value)
            ));
        }

        return parent::convertToDatabaseValue($value->toArray(), $platform);
    }
}
