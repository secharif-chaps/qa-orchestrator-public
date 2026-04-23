<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\ApifyFallbackNormalizerInterface;
use App\Domain\Collect\ApifyNormalizerResolverInterface;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\CollectException;
use App\Infrastructure\Collect\Apify\ApifyRunReference;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class ApifyNormalizerResolver implements ApifyNormalizerResolverInterface
{
    /**
     * @param iterable<ApifyDocumentNormalizerInterface> $normalizers tagged with 'apify.normalizer'
     */
    public function __construct(
        #[AutowireIterator('apify.normalizer')]
        private iterable $normalizers,
        private ApifyFallbackNormalizerInterface $fallback,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function resolveFor(CollectTask $collectTask): ApifyDocumentNormalizerInterface
    {
        $actorType = $this->extractActorType($collectTask);
        if (null !== $actorType) {
            foreach ($this->normalizers as $normalizer) {
                if ($normalizer->supports($actorType)) {
                    return $normalizer;
                }
            }
        }

        return $this->fallback;
    }

    private function extractActorType(CollectTask $collectTask): ?string
    {
        $providerTaskId = $collectTask->getProviderTaskId();
        if (null === $providerTaskId || '' === $providerTaskId) {
            return null;
        }

        try {
            return ApifyRunReference::fromProviderTaskId($providerTaskId)->actorId;
        } catch (CollectException) {
            $this->logger?->warning('Could not parse actor type from providerTaskId, using generic normalizer', [
                'provider_task_id' => $providerTaskId,
            ]);

            return null;
        }
    }
}
