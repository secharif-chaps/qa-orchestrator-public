<?php

declare(strict_types=1);

namespace App\Domain\Collect\Url;

use App\Domain\Source\SourceType;

/**
 * Map a free-form URL provided by the user (CLI `document:create --url=…`,
 * API POST /watch_files/{id}/documents body) to the {@see SourceType} that
 * best describes it.
 *
 * Used by the ad-hoc ingestion entrypoints to decide whether the URL can be
 * handled by the inline `web` provider (HTML article on a generic site →
 * `MANUAL`) or whether it points to a structured platform that requires a
 * dedicated provider (Twitter, LinkedIn, YouTube, TikTok, …) which we don't
 * support in one-shot mode yet.
 *
 * Implementations must:
 * - Return `null` when the URL is malformed or has no host;
 * - Return `SourceType::MANUAL` for unknown/generic web hosts (default
 *   fallback so the operator can still ingest a regular article);
 * - Return the most specific `SourceType` value that matches the host for
 *   recognised platforms — granularity (user vs hashtag vs search) does
 *   not need to be perfect; the caller only branches on social-vs-not.
 *
 * Stateless, side-effect free.
 */
interface UrlSourceTypeClassifierInterface
{
    public function classify(string $url): ?SourceType;
}
