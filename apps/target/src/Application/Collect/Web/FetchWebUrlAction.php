<?php

declare(strict_types=1);

namespace App\Application\Collect\Web;

use App\Application\SyncActionInterface;

/**
 * Drives the inline web ingestion path: given a {@see CollectTask} whose
 * `configuration` carries either a `url` (cloudflare-style fetch via
 * {@see HtmlFetcherInterface}) or a `raw_html` (manual paste), the matching
 * handler builds a {@see Document} and dispatches it through the standard
 * {@see IngestDocumentAction} ingestion bus.
 *
 * Implements {@see SyncActionInterface} so each occurrence runs inline on
 * the current transport context — symmetric with {@see FetchApifyDatasetAction}.
 * The `$sync` flag is the **chain forwarder**: when `true`, the handler
 * adds {@see TransportNamesStamp} `['sync']` on the downstream
 * `IngestDocumentAction` so the post-save pipeline also runs inline. CLI
 * `--sync` flips this on; the async API path leaves it `false` so the
 * post-save pipeline keeps its dedicated `quality_processing` transport.
 */
readonly class FetchWebUrlAction implements SyncActionInterface
{
    public function __construct(
        public string $collectTaskId,
        public bool $sync = false,
    ) {
    }
}
