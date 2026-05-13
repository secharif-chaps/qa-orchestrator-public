<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PostSaveDocumentProcessorInterface;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\DomainNormalizer;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Webmozart\Assert\Assert;

/**
 * Scores a document by the reputation of its top-level domain.
 *
 * Lists are static and intentionally conservative — a TLD's bad reputation
 * does not block content, it only weighs the `infrastructure_trust`
 * category down. Combined with other signals, this remains explainable and
 * recoverable when the rest of the document scores well.
 */
#[AsTaggedItem(priority: 95)]
class TldReputationProcessor implements PostSaveDocumentProcessorInterface
{
    private const string SIGNAL_KEY = 'tld_reputation';
    private const float WEIGHT = 1.0;
    private const float UNPARSABLE_SCORE = 0.30;
    private const float NEUTRAL_SCORE = 0.60;

    /**
     * Suspicious TLDs strongly associated with abuse, spam, or low-quality
     * content farms. Values are calibrated within [0.15, 0.40] so that
     * even a perfect score in other dimensions cannot fully compensate
     * (see ADR-2026-004 — min-of-categories scoring).
     *
     * @var array<string, float>
     */
    private const array SUSPICIOUS_TLDS = [
        'tk' => 0.15,
        'ml' => 0.20,
        'ga' => 0.20,
        'cf' => 0.20,
        'gq' => 0.20,
        'top' => 0.30,
        'xyz' => 0.35,
        'click' => 0.25,
        'link' => 0.30,
        'work' => 0.35,
        'win' => 0.25,
        'loan' => 0.20,
        'download' => 0.25,
    ];

    /**
     * Trusted TLDs with implicit institutional backing.
     * Multi-label suffixes (e.g. `gouv.fr`, `gov.uk`) are matched first
     * because they are more specific than the base TLD.
     *
     * @var array<string, float>
     */
    private const array TRUSTED_TLDS = [
        'gouv.fr' => 0.95,
        'gov.uk' => 0.95,
        'gov.au' => 0.95,
        'gov.cn' => 0.95,
        'gov.in' => 0.95,
        'gov' => 0.95,
        'mil' => 0.95,
        'edu' => 0.90,
        'edu.au' => 0.90,
        'ac.uk' => 0.90,
        'int' => 0.90,
    ];

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $url = $context->document->getUrl();
        Assert::notNull($url, 'TldReputationProcessor requires a URL (guarded by supports())');

        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || '' === $host) {
            return $context->withSignal(self::SIGNAL_KEY, new Signal(
                value: self::UNPARSABLE_SCORE,
                weight: self::WEIGHT,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
                reason: new TranslatedText(
                    fr: "Impossible d'extraire le domaine de l'URL",
                    en: 'Unable to parse host from URL',
                ),
            ));
        }

        $tld = DomainNormalizer::tld($host);
        if (null === $tld) {
            return $context->withSignal(self::SIGNAL_KEY, new Signal(
                value: self::UNPARSABLE_SCORE,
                weight: self::WEIGHT,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
                reason: new TranslatedText(fr: 'TLD introuvable', en: 'TLD could not be determined'),
            ));
        }

        if (isset(self::TRUSTED_TLDS[$tld])) {
            return $context->withSignal(self::SIGNAL_KEY, new Signal(
                value: self::TRUSTED_TLDS[$tld],
                weight: self::WEIGHT,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
                reason: new TranslatedText(
                    fr: \sprintf('TLD institutionnel : .%s', $tld),
                    en: \sprintf('Trusted institutional TLD: .%s', $tld),
                ),
            ));
        }

        if (isset(self::SUSPICIOUS_TLDS[$tld])) {
            return $context->withSignal(self::SIGNAL_KEY, new Signal(
                value: self::SUSPICIOUS_TLDS[$tld],
                weight: self::WEIGHT,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
                reason: new TranslatedText(
                    fr: \sprintf('TLD à risque : .%s (forte association avec spam/abus)', $tld),
                    en: \sprintf('Suspicious TLD: .%s (strong spam/abuse association)', $tld),
                ),
            ));
        }

        return $context->withSignal(self::SIGNAL_KEY, new Signal(
            value: self::NEUTRAL_SCORE,
            weight: self::WEIGHT,
            category: SignalCategory::INFRASTRUCTURE_TRUST,
            reason: new TranslatedText(
                fr: \sprintf('TLD neutre : .%s', $tld),
                en: \sprintf('Neutral TLD: .%s', $tld),
            ),
        ));
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        return !$context->isHalted && null !== $context->document->getUrl();
    }
}
