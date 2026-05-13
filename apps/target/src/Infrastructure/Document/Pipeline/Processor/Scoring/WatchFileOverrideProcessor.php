<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PostSaveDocumentProcessorInterface;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\DomainNormalizer;
use App\Domain\Shared\TranslatedText;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Webmozart\Assert\Assert;

/**
 * Honors WatchFile-specific trusted/blocked domain lists.
 *
 * Runs first in the pipeline so a blocked domain halts the pipeline before
 * any further processing. Trusted domains emit a high `infrastructure_trust`
 * signal instead, without halting.
 *
 * Patterns support a single leading wildcard, e.g. `*.gov.fr` matches
 * `gov.fr`, `www.gov.fr` and `service.gov.fr`. The match is performed on
 * the normalized root domain (see `DomainNormalizer`).
 */
#[AsTaggedItem(priority: 100)]
class WatchFileOverrideProcessor implements PostSaveDocumentProcessorInterface
{
    private const string SIGNAL_KEY = 'watchfile_override';
    private const float TRUSTED_SCORE = 0.95;
    private const float WEIGHT = 2.0;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $url = $context->document->getUrl();
        Assert::notNull($url, 'WatchFileOverrideProcessor requires a URL (guarded by supports())');

        $domain = DomainNormalizer::rootFromUrl($url);
        if (null === $domain) {
            return $context;
        }

        $config = $context->watchFile->getQualityConfig();

        if ($this->matchesAny($domain, $config->blockedDomains)) {
            $this->logger?->info('Document halted by WatchFile blocked domain', [
                'domain' => $domain,
                'watch_file_id' => $context->watchFile->getId(),
            ]);

            return $context->withHalt(new TranslatedText(
                fr: \sprintf('Domaine "%s" bloqué par la configuration du dossier de veille', $domain),
                en: \sprintf('Domain "%s" is blocked by the WatchFile configuration', $domain),
            ));
        }

        if ($this->matchesAny($domain, $config->trustedDomains)) {
            return $context->withSignal(self::SIGNAL_KEY, new Signal(
                value: self::TRUSTED_SCORE,
                weight: self::WEIGHT,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
                reason: new TranslatedText(
                    fr: \sprintf('Domaine "%s" approuvé par le dossier de veille', $domain),
                    en: \sprintf('Domain "%s" is trusted by the WatchFile', $domain),
                ),
            ));
        }

        return $context;
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        return !$context->isHalted && null !== $context->document->getUrl();
    }

    /**
     * @param array<string> $patterns
     */
    private function matchesAny(string $domain, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $domain, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));
        if ('' === $pattern) {
            return false;
        }

        // Normalize the bare/pattern side so `WWW.Gov.FR`, `gouv.fr.`,
        // IDN hosts, etc. produce the same key as the document side.
        if (str_starts_with($pattern, '*.')) {
            $rawBare = substr($pattern, 2);
            $bare = DomainNormalizer::normalizeHost($rawBare) ?? $rawBare;

            return $domain === $bare || str_ends_with($domain, '.' . $bare);
        }

        $normalizedPattern = DomainNormalizer::normalizeHost($pattern);

        return null !== $normalizedPattern && $domain === $normalizedPattern;
    }
}
