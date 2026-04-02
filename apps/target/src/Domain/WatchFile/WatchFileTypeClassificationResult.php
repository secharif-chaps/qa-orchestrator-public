<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\TranslatedText;

readonly class WatchFileTypeClassificationResult
{
    /**
     * @param list<WatchFileTypeClassificationItem> $secondaryTypes
     * @param list<Topic>                           $topics
     * @param list<string>                          $keywords
     * @param list<string>                          $detectedEntities
     * @param list<SourceSuggestion>                $sourceSuggestions
     * @param list<ActorSuggestion>                 $actorSuggestions
     */
    public function __construct(
        public WatchFileTypeClassificationItem $primaryType,
        public array $secondaryTypes,
        public array $topics,
        public array $keywords,
        public array $detectedEntities,
        public string $userObjective,
        public ?string $geographicScope,
        public array $sourceSuggestions,
        public array $actorSuggestions,
        public DeepSearchReadiness $deepSearchReadiness,
    ) {
    }

    /**
     * Create from N8N workflow output array.
     *
     * @param array{
     *      primaryType: string,
     *      primarySubtype: string|null,
     *      confidenceScore: int,
     *      justification: array{en: string, fr: string},
     *      secondaryTypes: list<array{type: string, subtype: string|null, score: int, justification: array{en: string, fr: string}}>,
     *      topics: list<array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string}>,
     *      analysis: array{detectedKeywords: list<string>, detectedEntities: list<string>, userObjective: string, geographicScope: string|null},
     *      suggestions: array{
     *          actors: list<array{name: string, type: string, relevance: string, score: int}>,
     *          sources: list<array{name: string, url?: string, type: string, relevance: string, score: int}>,
     *          searchQueries: list<string>
     *      },
     *      deepSearchReadiness: array{ready: bool, reason: array{en: string, fr: string}, suggestedSearchQueries: list<string>}
     *  } $data
     */
    public static function fromArray(array $data): self
    {
        // Parse primary type
        $primaryType = new WatchFileTypeClassificationItem(
            type: MonitoringType::from($data['primaryType']),
            confidenceScore: $data['confidenceScore'],
            justification: TranslatedText::fromArray($data['justification']),
        );

        // Parse secondary types
        $secondaryTypes = [];
        foreach ($data['secondaryTypes'] as $secondary) {
            $secondaryTypes[] = new WatchFileTypeClassificationItem(
                type: MonitoringType::from($secondary['type']),
                confidenceScore: $secondary['score'],
                justification: TranslatedText::fromArray($secondary['justification']),
            );
        }

        // Parse topics
        $topics = [];
        foreach ($data['topics'] as $topic) {
            $topics[] = Topic::fromArray($topic);
        }

        // Parse source suggestions
        $sourceSuggestions = [];
        foreach ($data['suggestions']['sources'] as $source) {
            $sourceSuggestions[] = SourceSuggestion::fromArray($source);
        }

        // Parse actor suggestions
        $actorSuggestions = [];
        foreach ($data['suggestions']['actors'] as $actor) {
            $actorSuggestions[] = ActorSuggestion::fromArray($actor);
        }

        return new self(
            primaryType: $primaryType,
            secondaryTypes: $secondaryTypes,
            topics: $topics,
            keywords: $data['analysis']['detectedKeywords'],
            detectedEntities: $data['analysis']['detectedEntities'],
            userObjective: $data['analysis']['userObjective'],
            geographicScope: $data['analysis']['geographicScope'],
            sourceSuggestions: $sourceSuggestions,
            actorSuggestions: $actorSuggestions,
            deepSearchReadiness: DeepSearchReadiness::fromArray($data['deepSearchReadiness']),
        );
    }
}
