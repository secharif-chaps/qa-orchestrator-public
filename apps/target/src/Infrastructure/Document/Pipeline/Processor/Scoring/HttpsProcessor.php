<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PostSaveDocumentProcessorInterface;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Webmozart\Assert\Assert;

#[AsTaggedItem(priority: 75)]
class HttpsProcessor implements PostSaveDocumentProcessorInterface
{
    private const float HTTPS_SCORE = 0.80;
    private const float HTTP_SCORE = 0.35;
    private const float WEIGHT = 0.5;

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $url = $context->document->getUrl();
        Assert::notNull($url, 'HttpsProcessor requires a URL (guarded by supports())');

        $isHttps = str_starts_with($url, 'https://');

        return $context->withSignal('https', new Signal(
            value: $isHttps ? self::HTTPS_SCORE : self::HTTP_SCORE,
            weight: self::WEIGHT,
            category: SignalCategory::INFRASTRUCTURE_TRUST,
            reason: $isHttps
                ? new TranslatedText(fr: "L'URL utilise HTTPS", en: 'URL uses HTTPS')
                : new TranslatedText(fr: "L'URL n'utilise pas HTTPS", en: 'URL uses HTTP only (unusual in 2026)'),
        ));
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        return !$context->isHalted && null !== $context->document->getUrl();
    }
}
