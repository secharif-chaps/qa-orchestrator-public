<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\DocumentQuality\DocumentProcessorInterface;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 65)]
final class PublicationDateProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.5;

    // Score values per freshness bucket
    private const float SCORE_FUTURE = 0.25;
    private const float SCORE_FRESH = 0.85;
    private const float SCORE_RECENT = 0.80;
    private const float SCORE_MEDIUM = 0.65;
    private const float SCORE_OLD = 0.50;

    // Thresholds in days
    private const int FRESH_THRESHOLD_DAYS = 7;
    private const int RECENT_THRESHOLD_DAYS = 365;   // 1 year
    private const int MEDIUM_THRESHOLD_DAYS = 1095;  // 3 years

    public function process(ProcessingContext $context): ProcessingContext
    {
        $publishedAt = $context->document->getDatePublish();
        $now = new \DateTimeImmutable();

        if ($publishedAt > $now) {
            return $context->withSignal('publication_date', new Signal(
                value: self::SCORE_FUTURE,
                weight: self::WEIGHT,
                category: SignalCategory::METADATA,
                reason: new TranslatedText(
                    fr: 'Date de publication dans le futur (suspect)',
                    en: 'Future publication date (suspicious)',
                ),
            ));
        }

        $interval = $now->diff($publishedAt);
        $daysSincePublication = (int) $interval->days;

        if ($daysSincePublication < self::FRESH_THRESHOLD_DAYS) {
            return $context->withSignal('publication_date', new Signal(
                value: self::SCORE_FRESH,
                weight: self::WEIGHT,
                category: SignalCategory::METADATA,
                reason: new TranslatedText(
                    fr: \sprintf('Publié récemment : %s (< 7 jours)', $publishedAt->format('Y-m-d')),
                    en: \sprintf('Recently published: %s (< 7 days)', $publishedAt->format('Y-m-d')),
                ),
            ));
        }

        if ($daysSincePublication < self::RECENT_THRESHOLD_DAYS) {
            return $context->withSignal('publication_date', new Signal(
                value: self::SCORE_RECENT,
                weight: self::WEIGHT,
                category: SignalCategory::METADATA,
                reason: new TranslatedText(
                    fr: \sprintf('Publié le %s', $publishedAt->format('Y-m-d')),
                    en: \sprintf('Published: %s', $publishedAt->format('Y-m-d')),
                ),
            ));
        }

        if ($daysSincePublication < self::MEDIUM_THRESHOLD_DAYS) {
            $years = intdiv($daysSincePublication, 365);

            return $context->withSignal('publication_date', new Signal(
                value: self::SCORE_MEDIUM,
                weight: self::WEIGHT,
                category: SignalCategory::METADATA,
                reason: new TranslatedText(
                    fr: \sprintf(
                        'Contenu de %d an%s : %s',
                        $years,
                        $years > 1 ? 's' : '',
                        $publishedAt->format('Y-m-d')
                    ),
                    en: \sprintf(
                        'Content from %d year%s ago: %s',
                        $years,
                        $years > 1 ? 's' : '',
                        $publishedAt->format('Y-m-d')
                    ),
                ),
            ));
        }

        $years = intdiv($daysSincePublication, 365);

        return $context->withSignal('publication_date', new Signal(
            value: self::SCORE_OLD,
            weight: self::WEIGHT,
            category: SignalCategory::METADATA,
            reason: new TranslatedText(
                fr: \sprintf('Contenu ancien : il y a %d an%s', $years, $years > 1 ? 's' : ''),
                en: \sprintf('Old content: %d year%s ago', $years, $years > 1 ? 's' : ''),
            ),
        ));
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision() && $context->document->hasDatePublish();
    }
}
