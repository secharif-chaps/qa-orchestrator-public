<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PostSaveDocumentProcessorInterface;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 68)]
class ContentRatioProcessor implements PostSaveDocumentProcessorInterface
{
    private const float WEIGHT = 0.7;
    private const float MIN_RATIO = 0.10;
    private const float OPTIMAL_RATIO = 0.30;
    private const float SCORE_UNAVAILABLE = 0.50;
    private const float SCORE_LOW = 0.20;
    private const float SCORE_MODERATE = 0.55;
    private const float SCORE_GOOD = 0.80;

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $ratio = $context->document->getContentRatio();

        if (null === $ratio) {
            return $context->withSignal('content_ratio', new Signal(
                value: self::SCORE_UNAVAILABLE,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: 'Ratio contenu/HTML non disponible (contenu non collecté via HTML)',
                    en: 'Content ratio not available (content not collected from HTML)',
                ),
            ));
        }

        if ($ratio < self::MIN_RATIO) {
            return $context->withSignal('content_ratio', new Signal(
                value: self::SCORE_LOW,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf(
                        'Faible densité de contenu : %.1f%% (page de navigation ou publicitaire)',
                        $ratio * 100
                    ),
                    en: \sprintf('Low content ratio: %.1f%% (navigation/ad-heavy page)', $ratio * 100),
                ),
            ));
        }

        if ($ratio < self::OPTIMAL_RATIO) {
            return $context->withSignal('content_ratio', new Signal(
                value: self::SCORE_MODERATE,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf('Densité de contenu modérée : %.1f%%', $ratio * 100),
                    en: \sprintf('Moderate content ratio: %.1f%%', $ratio * 100),
                ),
            ));
        }

        return $context->withSignal('content_ratio', new Signal(
            value: self::SCORE_GOOD,
            weight: self::WEIGHT,
            category: SignalCategory::CONTENT_QUALITY,
            reason: new TranslatedText(
                fr: \sprintf('Bonne densité de contenu : %.1f%%', $ratio * 100),
                en: \sprintf('Good content ratio: %.1f%%', $ratio * 100),
            ),
        ));
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        $type = $context->document->getType();

        return !$context->isHalted && 'html' === $type;
    }
}
