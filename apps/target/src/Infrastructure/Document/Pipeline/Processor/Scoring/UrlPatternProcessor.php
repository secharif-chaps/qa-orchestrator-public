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

#[AsTaggedItem(priority: 85)]
class UrlPatternProcessor implements PostSaveDocumentProcessorInterface
{
    private const float WEIGHT = 0.9;

    /**
     * Negative URL path patterns indicating low editorial value.
     * First match wins. Scores range from 0.10 (authentication) to 0.40 (corporate info).
     *
     * @var array<string, array{score: float, fr: string, en: string}>
     */
    private const array NEGATIVE_PATTERNS = [
        '/\/(login|signin|auth|logout|register)\b/i' => [
            'score' => 0.10,
            'fr' => 'Page d\'authentification',
            'en' => 'Authentication page',
        ],
        '/\/(cart|checkout|basket|payment)\b/i' => [
            'score' => 0.15,
            'fr' => 'Page de transaction e-commerce',
            'en' => 'E-commerce transaction page',
        ],
        '/\/(privacy|terms|legal|cookie|gdpr|cgu|cgv)\b/i' => [
            'score' => 0.25,
            'fr' => 'Page légale ou politique',
            'en' => 'Legal/policy page',
        ],
        '/\/(search|recherche)\b/i' => [
            'score' => 0.20,
            'fr' => 'Page de résultats de recherche',
            'en' => 'Search results page',
        ],
        '/\/page\/\d+/i' => [
            'score' => 0.25,
            'fr' => 'Page de pagination',
            'en' => 'Pagination page',
        ],
        '/\/(tag|category|archive)\/[^\/]+$/i' => [
            'score' => 0.30,
            'fr' => 'Page de listing catégorie/tag',
            'en' => 'Category/tag listing page',
        ],
        '/\/(contact|about|a-propos|qui-sommes-nous)\b/i' => [
            'score' => 0.40,
            'fr' => 'Page institutionnelle',
            'en' => 'Corporate info page',
        ],
    ];

    /**
     * Positive URL path patterns indicating strong editorial intent.
     * First match wins. Scores range from 0.75 (press) to 0.85 (article/news).
     *
     * @var array<string, array{score: float, fr: string, en: string}>
     */
    private const array POSITIVE_PATTERNS = [
        '/\/(article|articles)\b/i' => [
            'score' => 0.85,
            'fr' => 'Article éditorial',
            'en' => 'Editorial article',
        ],
        '/\/news\b/i' => [
            'score' => 0.85,
            'fr' => 'Article d\'actualité',
            'en' => 'News article',
        ],
        '/\/(blog|billet)\b/i' => [
            'score' => 0.80,
            'fr' => 'Article de blog',
            'en' => 'Blog post',
        ],
        '/\/(press|presse|communique|communiqué|press-release)\b/i' => [
            'score' => 0.75,
            'fr' => 'Communiqué de presse',
            'en' => 'Press release',
        ],
    ];
    private const int MAX_URL_DEPTH = 5;

    /** Minimum number of dash-separated segments in a slug to flag keyword stuffing. */
    private const int KEYWORD_STUFFING_MIN_SEGMENTS = 6;
    private const float SCORE_DEEP_URL = 0.40;
    private const float SCORE_KEYWORD_STUFFING = 0.35;
    private const float DEFAULT_SCORE = 0.70;

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $url = $context->document->getUrl();
        Assert::notNull($url, 'UrlPatternProcessor requires a URL (guarded by supports())');

        $path = parse_url($url, \PHP_URL_PATH);
        $path = \is_string($path) ? $path : '';

        if ('' === $path || '/' === $path) {
            return $context->withSignal('url_pattern', new Signal(
                value: self::DEFAULT_SCORE,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(fr: 'URL racine (homepage)', en: 'Root URL (homepage)'),
            ));
        }

        // Positive patterns take priority: explicit editorial markers (article, news, blog, press)
        // outweigh incidental keyword matches in the path (e.g. /articles/gdpr-compliance)
        foreach (self::POSITIVE_PATTERNS as $pattern => $config) {
            if (1 === preg_match($pattern, $path)) {
                return $context->withSignal('url_pattern', new Signal(
                    value: $config['score'],
                    weight: self::WEIGHT,
                    category: SignalCategory::CONTENT_QUALITY,
                    reason: new TranslatedText(fr: $config['fr'], en: $config['en']),
                ));
            }
        }

        foreach (self::NEGATIVE_PATTERNS as $pattern => $config) {
            if (1 === preg_match($pattern, $path)) {
                return $context->withSignal('url_pattern', new Signal(
                    value: $config['score'],
                    weight: self::WEIGHT,
                    category: SignalCategory::CONTENT_QUALITY,
                    reason: new TranslatedText(fr: $config['fr'], en: $config['en']),
                ));
            }
        }

        $depth = substr_count(trim($path, '/'), '/');
        if ($depth > self::MAX_URL_DEPTH) {
            return $context->withSignal('url_pattern', new Signal(
                value: self::SCORE_DEEP_URL,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: \sprintf('Structure URL trop profonde : %d niveaux', $depth + 1),
                    en: \sprintf('Deep URL structure: %d levels', $depth + 1),
                ),
            ));
        }

        $slug = basename($path);
        $segments = explode('-', $slug);
        if (\count($segments) >= self::KEYWORD_STUFFING_MIN_SEGMENTS) {
            return $context->withSignal('url_pattern', new Signal(
                value: self::SCORE_KEYWORD_STUFFING,
                weight: self::WEIGHT,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(
                    fr: 'Bourrage de mots-clés potentiel dans le slug URL',
                    en: 'Potential keyword stuffing in URL slug',
                ),
            ));
        }

        return $context->withSignal('url_pattern', new Signal(
            value: self::DEFAULT_SCORE,
            weight: self::WEIGHT,
            category: SignalCategory::CONTENT_QUALITY,
            reason: new TranslatedText(fr: 'Structure URL standard', en: 'Standard URL structure'),
        ));
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        $url = $context->document->getUrl();

        // parse_url returns false for severely malformed URLs — skip scoring in that case.
        return !$context->isHalted
            && null !== $url
            && false !== parse_url($url);
    }
}
