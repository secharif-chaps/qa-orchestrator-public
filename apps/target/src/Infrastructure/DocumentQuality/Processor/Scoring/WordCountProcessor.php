<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\DocumentQuality\DocumentProcessorInterface;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 70)]
final class WordCountProcessor implements DocumentProcessorInterface
{
    private const float WEIGHT = 0.6;

    // Standard thresholds (articles, blog posts, reports)
    private const int STANDARD_MIN = 50;
    private const int STANDARD_TRANSITION = 200;
    private const int STANDARD_LONG = 1000;

    // Social media thresholds
    private const int SOCIAL_MIN = 10;
    private const int SOCIAL_TRANSITION = 50;

    // Score values
    private const float SCORE_TOO_SHORT = 0.20;
    private const float SCORE_SHORT = 0.60;
    private const float SCORE_OPTIMAL = 0.80;
    private const float SCORE_LONG = 0.90;
    private const float SCORE_SOCIAL_TOO_SHORT = 0.30;
    private const float SCORE_SOCIAL_SHORT = 0.60;
    private const float SCORE_SOCIAL_NORMAL = 0.80;

    /**
     * @var list<string>
     */
    private const array SOCIAL_MEDIA_DOMAINS = [
        'twitter.com',
        'x.com',
        'linkedin.com',
        'facebook.com',
        'instagram.com',
        'threads.net',
        'mastodon.social',
        'bsky.app',
        'reddit.com',
        't.me',
    ];

    public function process(ProcessingContext $context): ProcessingContext
    {
        $wordCount = $context->document->getWordCount();

        if ($this->isSocialMedia($context->document->getUrl())) {
            return $this->scoreSocialMedia($context, $wordCount);
        }

        return $this->scoreStandard($context, $wordCount);
    }

    public function supports(ProcessingContext $context): bool
    {
        return !$context->hasEarlyDecision() && $context->document->hasContent();
    }

    private function isSocialMedia(?string $url): bool
    {
        $host = $this->normalizeHost($url);

        if ('' === $host) {
            return false;
        }

        foreach (self::SOCIAL_MEDIA_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHost(?string $url): string
    {
        if (null === $url) {
            return '';
        }

        $host = (string) (parse_url($url, \PHP_URL_HOST) ?: '');
        $host = (string) (idn_to_ascii($host, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46) ?: $host);

        return (string) preg_replace('/^www\./i', '', strtolower($host));
    }

    private function scoreSocialMedia(ProcessingContext $context, int $wordCount): ProcessingContext
    {
        if ($wordCount < self::SOCIAL_MIN) {
            return $context->withSignal('word_count', new Signal(
                value: self::SCORE_SOCIAL_TOO_SHORT,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf('Post trop court : %d mot%s', $wordCount, $wordCount > 1 ? 's' : ''),
                    en: \sprintf('Social media post too short: %d word%s', $wordCount, $wordCount > 1 ? 's' : ''),
                ),
            ));
        }

        if ($wordCount <= self::SOCIAL_TRANSITION) {
            return $context->withSignal('word_count', new Signal(
                value: self::SCORE_SOCIAL_SHORT,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf('Post court : %d mots', $wordCount),
                    en: \sprintf('Short social media post: %d words', $wordCount),
                ),
            ));
        }

        return $context->withSignal('word_count', new Signal(
            value: self::SCORE_SOCIAL_NORMAL,
            weight: self::WEIGHT,
            category: SignalCategory::CONTENT_QUALITY,
            reason: new TranslatedText(
                fr: \sprintf('Post social media : %d mots', $wordCount),
                en: \sprintf('Social media post: %d words', $wordCount),
            ),
        ));
    }

    private function scoreStandard(ProcessingContext $context, int $wordCount): ProcessingContext
    {
        if ($wordCount < self::STANDARD_MIN) {
            return $context->withSignal('word_count', new Signal(
                value: self::SCORE_TOO_SHORT,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf(
                        'Trop court : %d mot%s (minimum : %d)',
                        $wordCount,
                        $wordCount > 1 ? 's' : '',
                        self::STANDARD_MIN
                    ),
                    en: \sprintf(
                        'Too short: %d word%s (min: %d)',
                        $wordCount,
                        $wordCount > 1 ? 's' : '',
                        self::STANDARD_MIN
                    ),
                ),
            ));
        }

        if ($wordCount < self::STANDARD_TRANSITION) {
            return $context->withSignal('word_count', new Signal(
                value: self::SCORE_SHORT,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf('Contenu court : %d mots', $wordCount),
                    en: \sprintf('Short content: %d words', $wordCount),
                ),
            ));
        }

        if ($wordCount <= self::STANDARD_LONG) {
            return $context->withSignal('word_count', new Signal(
                value: self::SCORE_OPTIMAL,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf('Longueur optimale : %d mots', $wordCount),
                    en: \sprintf('Optimal length: %d words', $wordCount),
                ),
            ));
        }

        return $context->withSignal('word_count', new Signal(
            value: self::SCORE_LONG,
            weight: self::WEIGHT,
            category: SignalCategory::CONTENT_QUALITY,
            reason: new TranslatedText(
                fr: \sprintf('Contenu long : %d mots', $wordCount),
                en: \sprintf('Long content: %d words', $wordCount),
            ),
        ));
    }
}
