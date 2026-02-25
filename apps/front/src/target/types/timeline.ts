import type { Source, SourceActivity } from '@target/types/source'
import type { WatchFileActivity, WatchFileActor, WatchFileEventType } from '@target/types/watchFile'
import type { JsonLdResource } from './jsonld'

export interface WatchFileActivityDescription {
  dataType: string
  localizationKey: string
  activity: WatchFileActivity
  customComponent?: string
}

export interface SourceActivityDescription {
  dataType: string
  localizationKey: string
  activity: SourceActivity
  customComponent?: string
}

export type ActivityDescription = WatchFileActivityDescription | SourceActivityDescription

export interface TimelineActivity {
  id: string
  time: string
  message: ActivityDescription
  type: WatchFileEventType
  color: string
  icon: string
  user?: {
    '@id': string
    '@type': string
    id: string
    email: string
  }
  button?: {
    text: string
    icon?: string
    action: () => void
  }
}

export interface TimelineDay {
  date: string
  activities: TimelineActivity[]
}

export interface TimelineProps {
  days: TimelineDay[]
  isLoading?: boolean
  emptyMessage?: string
  hasNextPage?: boolean
  isLoadingMore?: boolean
}

export interface TimelineEventParams {
  watchFileId: string
  eventId: string
}

export interface TimelineEventUser {
  id: string
  name: string
  email: string
}

export interface TimelineEventMetadata {
  actor_name: string
  actor_id: string
  actor_primary_domain: string
  status: string
  old_status: string
}

export interface TimelineEvent {
  id: string
  timestamp: string
  context: string
  user: TimelineEventUser
}
export interface TimelineEventActor extends TimelineEvent {
  user: TimelineEventUser
  metadata: TimelineEventMetadata
}
export interface TimelineEventSource extends TimelineEvent {
  type: string
  actionData: Record<string, string>
}

export interface TimelineEventActors extends JsonLdResource {
  event: TimelineEventActor
  actors: WatchFileActor[]
  count: number
}

export interface TimelineEventSources extends JsonLdResource {
  event: TimelineEvent
  sources: Source[]
  count: number
}
