<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Type;

use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Doctrine custom type for QualityReport signals.
 * Stores array<string, Signal> as JSONB in the database.
 */
final class SignalsType extends JsonType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }

    /**
     * @return array<string, Signal>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (\is_array($value)) {
            /** @var array<string, Signal> $value */
            return $value;
        }

        /** @var array<string, array{value: float, weight: float, category: string, reason: array{fr: string, en: string}|null}> $raw */
        $raw = parent::convertToPHPValue($value, $platform);

        $signals = [];
        foreach ($raw as $name => $data) {
            $signals[$name] = new Signal(
                value: (float) $data['value'],
                weight: (float) $data['weight'],
                category: SignalCategory::from($data['category']),
                reason: isset($data['reason']) ? TranslatedText::fromArray($data['reason']) : null,
            );
        }

        return $signals;
    }

    /**
     * @param array<string, Signal> $value
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        $raw = [];
        foreach ($value as $name => $signal) {
            $raw[$name] = [
                'value' => $signal->value,
                'weight' => $signal->weight,
                'category' => $signal->category->value,
                'reason' => $signal->reason?->toArray(),
            ];
        }

        return (string) parent::convertToDatabaseValue($raw, $platform);
    }
}
