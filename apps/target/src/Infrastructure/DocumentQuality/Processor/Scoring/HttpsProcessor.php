<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\DocumentQuality\DocumentProcessorInterface;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 75)]
final class HttpsProcessor implements DocumentProcessorInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        $url = $context->document->getUrl();
        $isHttps = null !== $url && str_starts_with($url, 'https://');

        return $context->withSignal('https', new Signal(
            value: $isHttps ? 1.0 : 0.0,
            weight: 1.0,
            category: SignalCategory::INFRASTRUCTURE_TRUST,
            reason: $isHttps
                ? new TranslatedText(fr: "L'URL utilise HTTPS", en: 'URL uses HTTPS')
                : new TranslatedText(fr: "L'URL n'utilise pas HTTPS", en: 'URL does not use HTTPS'),
        ));
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision();
    }
}
