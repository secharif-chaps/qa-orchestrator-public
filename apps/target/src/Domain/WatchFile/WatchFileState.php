<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum WatchFileState: string
{
    case NEW = 'new';
    case NEEDS_ANALYZED = 'needs_analyzed';
    case QUESTIONS_GENERATED = 'questions_generated';
    case SEARCH_QUERY_GENERATED = 'search_query_generated';
    case SEARCH_RESULTS_RETRIEVED = 'search_results_retrieved';
    case FILTER_URLS = 'filter_urls';
    case TEMPORAL_FRAMING = 'temporal_framing';
    case ACTORS_DETECTED = 'actors_detected';
    case SOURCES_DETECTED = 'sources_detected';
    case MONITORING_TYPE_DETECTED = 'monitoring_type_detected';
    case REFERENCE_SUBJECT_DETECTED = 'reference_subject_detected';
    case FAILED = 'failed';
    private const array TRANSITION = [
        'new' => [self::NEEDS_ANALYZED, self::MONITORING_TYPE_DETECTED, self::REFERENCE_SUBJECT_DETECTED],
        'needs_analyzed' => [self::QUESTIONS_GENERATED],
        'questions_generated' => [self::SEARCH_QUERY_GENERATED],
        'search_query_generated' => [self::SEARCH_RESULTS_RETRIEVED],
        'search_results_retrieved' => [self::FILTER_URLS],
        'filter_urls' => [self::TEMPORAL_FRAMING],
        'temporal_framing' => [self::ACTORS_DETECTED],
        'actors_detected' => [self::SOURCES_DETECTED],
        'sources_detected' => [self::MONITORING_TYPE_DETECTED],
        'monitoring_type_detected' => [self::REFERENCE_SUBJECT_DETECTED],
    ];

    public function canTransitionTo(self $targetState): bool
    {
        if ($this->value === $targetState->value) {
            return true;
        }

        return isset(self::TRANSITION[$this->value]) && \in_array($targetState, self::TRANSITION[$this->value], true);
    }

    public function throwIfInvalidTransition(self $targetState): void
    {
        if (!$this->canTransitionTo($targetState)) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot transition from %s to %s',
                $this->value,
                $targetState->value,
            ));
        }
    }

    public function isTerminal(): bool
    {
        return empty(self::TRANSITION[$this->value]);
    }
}
