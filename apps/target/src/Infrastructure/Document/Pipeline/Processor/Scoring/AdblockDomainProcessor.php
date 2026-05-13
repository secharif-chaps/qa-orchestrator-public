<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PostSaveDocumentProcessorInterface;
use App\Domain\DocumentQuality\AdblockDomainListProviderInterface;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\DomainNormalizer;
use App\Domain\Shared\TranslatedText;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Webmozart\Assert\Assert;

/**
 * Scores a document by checking its root domain against community-maintained
 * adblock and malware lists (StevenBlack, EasyList, …).
 *
 * Provider failures are treated as a soft-fail: when the list is unavailable
 * the processor returns the context unchanged rather than emitting a
 * potentially misleading "clean" signal.
 */
#[AsTaggedItem(priority: 88)]
class AdblockDomainProcessor implements PostSaveDocumentProcessorInterface
{
    private const string SIGNAL_KEY = 'adblock_domain';
    private const float WEIGHT = 0.8;
    private const float CLEAN_SCORE = 0.85;
    private const float FLAGGED_SCORE = 0.25;

    public function __construct(
        private readonly AdblockDomainListProviderInterface $provider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $url = $context->document->getUrl();
        Assert::notNull($url, 'AdblockDomainProcessor requires a URL (guarded by supports())');

        $domain = DomainNormalizer::rootFromUrl($url);
        if (null === $domain) {
            return $context;
        }

        try {
            $match = $this->provider->findMatch($domain);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Adblock provider unavailable, skipping signal', [
                'domain' => $domain,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $context;
        }

        if (null !== $match) {
            return $context->withSignal(self::SIGNAL_KEY, new Signal(
                value: self::FLAGGED_SCORE,
                weight: self::WEIGHT,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
                reason: new TranslatedText(
                    fr: \sprintf(
                        'Domaine "%s" présent dans la liste adblock "%s" (catégorie : %s)',
                        $match->domain,
                        $match->sourceName,
                        $match->category->value,
                    ),
                    en: \sprintf(
                        'Domain "%s" found in adblock list "%s" (category: %s)',
                        $match->domain,
                        $match->sourceName,
                        $match->category->value,
                    ),
                ),
            ));
        }

        return $context->withSignal(self::SIGNAL_KEY, new Signal(
            value: self::CLEAN_SCORE,
            weight: self::WEIGHT,
            category: SignalCategory::INFRASTRUCTURE_TRUST,
            reason: new TranslatedText(
                fr: \sprintf('Domaine "%s" absent des listes adblock', $domain),
                en: \sprintf('Domain "%s" not found in adblock lists', $domain),
            ),
        ));
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        return !$context->isHalted && null !== $context->document->getUrl();
    }
}
