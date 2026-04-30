<?php

declare(strict_types=1);

namespace App\Domain\Document;

/**
 * Build a {@see Document} from a parsed {@see HtmlMetadata} payload.
 *
 * The same construction logic is needed by the manual ingestion entrypoints
 * — the API Platform `CreateDocumentProcessor` and the CLI `document:create`
 * command. Centralising it here keeps the two paths byte-for-byte identical
 * (title/excerpt/url/contentRatio/providerId) and prevents drift.
 *
 * Stateless, no I/O — pure factory living in the Domain.
 */
readonly class DocumentBuilderFromHtmlMetadata
{
    /**
     * @param string|null $titleOverride   When non-null, replaces metadata.title
     * @param string|null $excerptOverride When non-null, replaces metadata.excerpt
     * @param string|null $sourceUrl       The original URL the HTML was fetched from
     *                                     (`null` when the caller posted raw HTML)
     * @param string      $rawHtml         Raw HTML string used to compute the
     *                                     content/HTML ratio and a fallback hash
     */
    public function build(
        HtmlMetadata $metadata,
        string $rawHtml,
        ?string $sourceUrl,
        ?string $titleOverride = null,
        ?string $excerptOverride = null,
    ): Document {
        $title = $titleOverride ?? $metadata->title;
        $excerpt = $excerptOverride ?? $metadata->excerpt;
        $datePublish = $metadata->datePublish ?? new \DateTimeImmutable();

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: new \DateTimeImmutable(),
            content: $metadata->content,
            cfcRestricted: false,
            url: $metadata->canonicalUrl ?? $sourceUrl,
        );

        $document->setLanguage($metadata->language);

        $htmlLength = mb_strlen($rawHtml);
        $document->setContentRatio($htmlLength > 0 ? mb_strlen($metadata->content) / $htmlLength : null);

        // Provider id is the dedup key for this manual ingest. Prefer the
        // canonical URL when present (more stable than redirect URLs); fall
        // back to the source URL, then to a hash of the first 10k HTML bytes.
        $dedupUrl = $metadata->canonicalUrl ?? $sourceUrl;
        // substr (byte-based) is fine here — the bytes feed into a hash, no
        // need for codepoint-aware truncation, and it stays O(1) on large HTML.
        $providerId = null !== $dedupUrl
            ? hash('sha256', $dedupUrl)
            : hash('sha256', substr($rawHtml, 0, 10000));
        $document->setProviderId($providerId);

        return $document;
    }
}
