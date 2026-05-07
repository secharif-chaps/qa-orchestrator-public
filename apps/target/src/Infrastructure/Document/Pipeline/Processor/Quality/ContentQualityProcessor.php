<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Quality;

use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PipelinePhase;
use App\Domain\Document\Pipeline\PreSaveDocumentProcessorInterface;
use App\Domain\Shared\TranslatedText;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Provider-agnostic quality gate run as the first step of the pre-save
 * pipeline.
 *
 * Catches **catastrophic** content issues that should never make it into
 * OpenSearch regardless of where the Document came from:
 *
 * - The page title looks like an error / paywall / captcha (Cloudflare
 *   challenge, "403 Forbidden", "Just a moment…", "Attention required",
 *   etc.). These are signals that the upstream provider returned the
 *   bot-detection chrome instead of the article body.
 * - The text content (after stripping HTML tags) is essentially empty —
 *   under {@see self::ABSOLUTE_MIN_CONTENT_LENGTH} characters of trimmed
 *   plain text. JavaScript-rendered SPAs, paywalled bodies and bot
 *   challenges produce HTML hulls that pass other checks but contain no
 *   real article.
 *
 * Priority 290 (high in the {@see PipelinePhase::ENRICHMENT} range, 200-299)
 * so we halt before fingerprint computation and dedup look-ups — there is
 * no point fingerprinting an error page.
 *
 * The richer "soft" check from the legacy CLI/API path (sub-200-char
 * content allowed when strong metadata vouches for the page) is
 * intentionally NOT run here: it requires `siteName`/`author` which the
 * Document entity does not carry. Web/manual providers historically used
 * that signal because their HtmlMetadataExtractor surfaced it inline; we
 * accept the small risk of rejecting genuinely sub-200-char articles in
 * exchange for one universal check that also covers Apify/Bakus.
 */
#[AsTaggedItem(priority: 290)]
readonly class ContentQualityProcessor implements PreSaveDocumentProcessorInterface
{
    /**
     * Hard floor on the post-strip_tags text length. A page that produces
     * less than this much real text is considered empty regardless of any
     * upstream signal. Matches {@see HtmlMetadata::ABSOLUTE_MIN_CONTENT_LENGTH}.
     */
    private const int ABSOLUTE_MIN_CONTENT_LENGTH = 50;

    /**
     * Substrings that cause a halt when they appear in the (lowercased)
     * page title. Mirrors {@see HtmlMetadata::ERROR_TITLE_PATTERNS}.
     */
    private const array ERROR_TITLE_PATTERNS = [
        'access denied',
        'forbidden',
        '403',
        '404',
        'not found',
        'page not found',
        'error',
        'blocked',
        'captcha',
        'please verify',
        'just a moment',
        'attention required',
        'checking your browser',
        'enable javascript',
        'enable cookies',
        'unauthorized',
        'login required',
        'sign in',
        'subscribe to continue',
        'paywall',
    ];

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $document = $context->document;
        $title = $document->getTitle();
        $content = $document->getContent();

        $lowerTitle = mb_strtolower($title);
        foreach (self::ERROR_TITLE_PATTERNS as $pattern) {
            if (str_contains($lowerTitle, $pattern)) {
                return $context->withHalt(new TranslatedText(
                    fr: \sprintf('Le titre suggère une page d\'erreur ou bloquée : "%s".', $title),
                    en: \sprintf('Page title suggests an error or blocked page: "%s".', $title),
                ));
            }
        }

        $textLength = mb_strlen(trim(strip_tags($content)));
        if ($textLength < self::ABSOLUTE_MIN_CONTENT_LENGTH) {
            return $context->withHalt(new TranslatedText(
                fr: \sprintf(
                    'Corps essentiellement vide (%d caractères après strip_tags) — vraisemblablement une page rendue en JS, paywall ou anti-bot.',
                    $textLength,
                ),
                en: \sprintf(
                    'Content body essentially empty (%d chars after strip_tags) — likely a JS-rendered page, paywall or bot-detection chrome.',
                    $textLength,
                ),
            ));
        }

        return $context;
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        if ($context->isHalted) {
            return false;
        }

        return '' !== $context->document->getTitle() || '' !== $context->document->getContent();
    }
}
