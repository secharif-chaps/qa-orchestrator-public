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
     * Single-word patterns matched as **whole words** (`\b…\b`). Substring
     * matching used to false-positive on legitimate titles ("Sherlock and
     * the Erroneous Detective" matched on `error`, "Background Hollywood"
     * matched on `blocked`). The word-boundary regex keeps the original
     * intent (catch "Forbidden", "Access denied", "PAYWALL") without
     * those collateral hits.
     */
    private const string ERROR_WORD_REGEX = '/\b(error|blocked|forbidden|unauthorized|paywall|denied)\b/iu';

    /**
     * Multi-word phrases matched as plain substrings. Their composition is
     * specific enough that substring matching does not produce false
     * positives, so the cheaper `str_contains` is preserved.
     */
    private const array ERROR_PHRASE_PATTERNS = [
        'access denied',
        'not found',
        'page not found',
        'captcha',
        'please verify',
        'just a moment',
        'attention required',
        'checking your browser',
        'enable javascript',
        'enable cookies',
        'login required',
        'sign in',
        'subscribe to continue',
    ];

    /**
     * HTTP status codes triggering a halt — kept as plain `str_contains`
     * since digits don't have word-boundary issues and "403" / "404" stay
     * unambiguous in a page title.
     */
    private const array ERROR_NUMERIC_PATTERNS = ['403', '404'];

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $document = $context->document;
        $title = $document->getTitle();
        $content = $document->getContent();

        if ('' !== $title && $this->titleLooksLikeErrorPage($title)) {
            return $context->withHalt(new TranslatedText(
                fr: \sprintf('Le titre suggère une page d\'erreur ou bloquée : "%s".', $title),
                en: \sprintf('Page title suggests an error or blocked page: "%s".', $title),
            ));
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

    private function titleLooksLikeErrorPage(string $title): bool
    {
        if (1 === preg_match(self::ERROR_WORD_REGEX, $title)) {
            return true;
        }

        $lowerTitle = mb_strtolower($title);
        foreach (self::ERROR_PHRASE_PATTERNS as $pattern) {
            if (str_contains($lowerTitle, $pattern)) {
                return true;
            }
        }

        foreach (self::ERROR_NUMERIC_PATTERNS as $pattern) {
            if (str_contains($title, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
