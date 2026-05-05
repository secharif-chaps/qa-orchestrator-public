<?php

declare(strict_types=1);

namespace App\Domain\Document;

readonly class HtmlMetadata
{
    public const string UNTITLED = 'Untitled Document';
    private const int MIN_CONTENT_LENGTH = 200;
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

    public function __construct(
        public string $title,
        public string $excerpt,
        public string $content,
        public string $language,
        public ?\DateTimeImmutable $datePublish,
        public ?string $imageUrl = null,
        public ?string $canonicalUrl = null,
        public ?string $author = null,
        public ?string $siteName = null,
    ) {
    }

    /**
     * Absolute floor on the post-strip_tags text length. A page that
     * produces less than this much real text is considered empty
     * regardless of metadata signals (covers JS-rendered SPAs, paywalls,
     * cookie walls and bot-detection chrome whose static HTML is just
     * `<link>` / `<script>` tags around an empty `<main>`). Distinct
     * from {@see MIN_CONTENT_LENGTH} which is the threshold for
     * accepting a short page when no metadata vouches for it.
     */
    private const int ABSOLUTE_MIN_CONTENT_LENGTH = 50;

    /**
     * Check if the extracted content looks like a real page (not an error, captcha, or paywall).
     * Returns null if valid, or an error reason string if not.
     */
    public function getContentIssue(): ?string
    {
        // First check: is the title an error page?
        $lowerTitle = strtolower($this->title);
        foreach (self::ERROR_TITLE_PATTERNS as $pattern) {
            if (str_contains($lowerTitle, $pattern)) {
                return \sprintf('Page title suggests an error or blocked page: "%s"', $this->title);
            }
        }

        $textContent = strip_tags($this->content);
        $textLength = mb_strlen(trim($textContent));

        // Hard floor — even strong metadata can't vouch for a page that
        // has effectively no body. JS-rendered SPAs, paywalls, and
        // bot-detection chrome end up here.
        if ($textLength < self::ABSOLUTE_MIN_CONTENT_LENGTH) {
            return \sprintf(
                'Content body essentially empty (%d chars after strip_tags) — likely a JS-rendered page, paywall or bot-detection chrome',
                $textLength,
            );
        }

        // Soft floor — allow short pages through when metadata vouches
        // for them (real articles can be sub-200-chars on launch / TLDR).
        $hasStrongMetadata = null !== $this->siteName || null !== $this->author;
        if ($textLength < self::MIN_CONTENT_LENGTH && !$hasStrongMetadata) {
            return \sprintf(
                'Content too short (%d chars, minimum %d) and no metadata to confirm legitimacy',
                $textLength,
                self::MIN_CONTENT_LENGTH
            );
        }

        return null;
    }
}
