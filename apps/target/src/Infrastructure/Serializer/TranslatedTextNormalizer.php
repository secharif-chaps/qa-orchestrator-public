<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\Shared\TranslatedText;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TranslatedTextNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$object instanceof TranslatedText) {
            throw new \InvalidArgumentException('Expected TranslatedText object');
        }

        return [
            'fr' => $object->fr,
            'en' => $object->en,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof TranslatedText;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): TranslatedText
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('TranslatedText data must be an array with fr and en keys');
        }

        if (!isset($data['fr']) || !isset($data['en'])) {
            throw new \InvalidArgumentException('TranslatedText data must contain both fr and en keys');
        }

        if (!\is_string($data['fr']) || !\is_string($data['en'])) {
            throw new \InvalidArgumentException('TranslatedText fr and en values must be strings');
        }

        return new TranslatedText($data['fr'], $data['en']);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return TranslatedText::class === $type && \is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            TranslatedText::class => true,
        ];
    }
}
