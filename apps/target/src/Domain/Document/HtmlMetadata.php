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

        // Content length check — but skip if we have strong metadata signals (real article)
        $textContent = strip_tags($this->content);
        $textLength = mb_strlen(trim($textContent));
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
