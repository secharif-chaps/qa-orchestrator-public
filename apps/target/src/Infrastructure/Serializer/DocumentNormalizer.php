<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\Document\Document;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class DocumentNormalizer implements NormalizerInterface
{
    private const string ALLOWED_FORMAT = 'elasticsearch'; // API Platform internal format name - do not change

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $objectNormalizer,
    ) {
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array|string|int|float|bool|\ArrayObject|null {
        if ($data instanceof Document) {
            $context = array_merge($context, [
                'groups' => [DocumentDenormalizer::SERIALIZATION_GROUP],
            ]);

            return $this->objectNormalizer->normalize($data, $format, $context);
        }

        return null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Document && self::ALLOWED_FORMAT === $format;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Document::class => true,
        ];
    }
}
