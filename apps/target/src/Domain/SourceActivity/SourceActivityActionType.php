<?php

declare(strict_types=1);

namespace App\Domain\SourceActivity;

enum SourceActivityActionType: string
{
    case SOURCE_CONNECTED = 'source_connected';
    case SOURCE_DISCONNECTED = 'source_disconnected';
    case SOURCE_ERROR = 'source_error';
    case SOURCE_RECOVERED = 'source_recovered';
    case SOURCE_DATA_RETRIEVED = 'source_data_retrieved';
    case SOURCE_CONFIG_UPDATED = 'source_config_updated';
    case SOURCE_STATUS_CHANGED = 'source_status_changed';
    case SOURCE_ADDED_TO_WATCHFILE = 'source_added_to_watchfile';
    case SOURCE_COLLECTOR_STATUS_CHANGED = 'source_collect_status_changed';
    case SOURCE_QUERY_LOG = 'source_query_log';
    case SOURCE_COLLECT_LOG = 'source_collect_log';
}
