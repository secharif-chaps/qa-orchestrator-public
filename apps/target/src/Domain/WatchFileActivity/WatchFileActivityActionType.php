<?php

declare(strict_types=1);

namespace App\Domain\WatchFileActivity;

enum WatchFileActivityActionType: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case SOURCE_STATUS_CHANGED = 'source_status_changed';
    case STATUS_CHANGED = 'status_changed';
    case ACTOR_STATUS_CHANGED = 'actor_status_changed';
    case MONITORING_TYPE_DETECTED = 'monitoring_type_detected';
    case REFERENCE_SUBJECT_UPDATED = 'reference_subject_updated';
    case SHARED_MODE_CHANGED = 'shared_mode_changed';
    case ACTOR_ADDED = 'actor_added';
    case SOURCE_ADDED = 'source_added';
    case DOCUMENT_QUOTA_EXCEEDED = 'document_quota_exceeded';
}
