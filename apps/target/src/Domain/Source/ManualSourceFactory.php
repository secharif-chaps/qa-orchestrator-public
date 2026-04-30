<?php

declare(strict_types=1);

namespace App\Domain\Source;

use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;

/**
 * Factory for the singleton manual {@see Source} attached to each WatchFile.
 *
 * The manual ingestion entrypoints — the API Platform `CreateDocumentProcessor`
 * (HTTP) and the `document:create` CLI command — both need to attach incoming
 * documents to a Source of type `MANUAL`. They first look up an existing
 * manual source on the watchfile, and create one on the fly when none exists.
 *
 * Centralising the construction here keeps the wording (`'Manual'`,
 * `'manual://…'`, the French/English translated descriptions) identical
 * across both paths and prevents drift.
 *
 * Stateless, no I/O — pure factory living in the Domain.
 */
readonly class ManualSourceFactory
{
    public function buildFor(WatchFile $watchFile): Source
    {
        return new Source(
            name: 'Manual',
            description: new TranslatedText('Source manuelle', 'Manual source'),
            type: SourceType::MANUAL,
            url: \sprintf('manual://%s', $watchFile->getId()),
            primaryDomain: 'manual',
            relevance: new TranslatedText('Ajout manuel par l\'utilisateur', 'Manually added by user'),
            actor: null,
            watchFile: $watchFile,
        );
    }
}
