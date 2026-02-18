import type { SortOrder } from '@owlint/feathers-vue'
import type { Actor, ActorStatus } from '~/types/actor'
import type { JsonLdResource } from '~/types/jsonld'
import type { Localized } from '~/types/localized'

export const WATCH_FILE_STATE = {
  NEW: 'new',
  NEEDS_ANALYZED: 'needs_analyzed',
  QUESTIONS_GENERATED: 'questions_generated',
  SEARCH_QUERY_GENERATED: 'search_query_generated',
  SEARCH_RESULTS_RETRIEVED: 'search_results_retrieved',
  FILTER_URLS: 'filter_urls',
  TEMPORAL_FRAMING: 'temporal_framing',
  ACTORS_DETECTED: 'actors_detected',
  SOURCES_DETECTED: 'sources_detected',
  MONITORING_TYPE_DETECTED: 'monitoring_type_detected',
  FAILED: 'failed',
}

export const WATCH_FILE_STATUS = {
  DRAFT: 'draft',
  ARCHIVED: 'archived',
  ENABLED: 'enabled',
} as const

export const WATCH_FILE_STATUS_ICONS = {
  DRAFT: 'fa-file-lines',
  ENABLED: 'fa-play',
  ARCHIVED: 'fa-box-archive',
} as const

export type WatchFileState = (typeof WATCH_FILE_STATE)[keyof typeof WATCH_FILE_STATE]
export type WatchFileStatus = (typeof WATCH_FILE_STATUS)[keyof typeof WATCH_FILE_STATUS]

export interface WatchFileFilters {
  sortBy: string
  sortOrder: SortOrder
  page?: number
  itemsPerPage?: number
  name?: string
  onlyFavorites?: boolean
  includeArchived?: boolean
}

export enum WatchFileUserAccessState {
  NO_ACCESS = 'no_access',
  EDITOR = 'editor',
  VIEWER = 'viewer',
  ADMIN = 'admin',
  OWNER = 'owner',
}

export interface WatchFile extends JsonLdResource {
  id: string
  '@id': string
  name: string
  titleManuallySetByUser: boolean
  referenceSubject?: Localized
  status: WatchFileStatus
  createdAt: string
  updatedAt: string
  watchFileUsersCount: number
  isFavorite: boolean
  userEditable: boolean
}

/**
 * WatchFileActor: Represents the relationship between an Actor and a WatchFile.
 * Contains contextual metadata (status, explanations, type) specific to this association.
 * A same Actor can have different metadata in different WatchFiles.
 */
export interface WatchFileActor extends JsonLdResource {
  id: string
  actor: Actor
  type: string
  explanations?: Record<string, string>
  status: ActorStatus
  createdAt: string
  sourcesCount?: number
}

export enum WatchFileEventType {
  WATCHFILE_CREATED = 'WATCHFILE_CREATED',
  WATCHFILE_UPDATED = 'WATCHFILE_UPDATED',
  WATCHFILE_ACTOR_STATUS_CHANGED = 'WATCHFILE_ACTOR_STATUS_CHANGED',
  WATCHFILE_SOURCE_STATUS_CHANGED = 'WATCHFILE_SOURCE_STATUS_CHANGED',
  WATCHFILE_STATUS_CHANGED = 'WATCHFILE_STATUS_CHANGED',
  WATCHFILE_MONITORING_TYPE_DETECTED = 'WATCHFILE_MONITORING_TYPE_DETECTED',
  WATCHFILE_REFERENCE_SUBJECT_UPDATED = 'WATCHFILE_REFERENCE_SUBJECT_UPDATED',
  WATCHFILE_SHARED_MODE_CHANGED = 'WATCHFILE_SHARED_MODE_CHANGED',
  WATCHFILE_ACTOR_ADDED = 'WATCHFILE_ACTOR_ADDED',
  WATCHFILE_SOURCE_ADDED = 'WATCHFILE_SOURCE_ADDED',
}

// Action data types for different action types
export interface StatusChangedActionData {
  old_status: string
  new_status: string
}

export interface SourceStatusChangedActionData {
  status: string
  source_name: string
}

export interface ActorStatusChangedActionData {
  status: string
  name: string
}

export interface SharedModeChangedActionData {
  new_value: string
  user_email: string
}

export interface MonitoringTypeDetectedActionData {
  monitoring_type: string
}

export interface ReferenceSubjectDetectedActionData {
  subject: string
}

export interface ActorAddedActionData {
  name: string
  email: string
}

export interface SourceAddedActionData {
  name: string
  url: string
}

export interface UpdatedActionData {
  name?: {
    old: string
    new: string
  }
  description?: {
    old: string
    new: string
  }
}

// Union type for all possible action data
export type WatchFileActivityActionData =
  | StatusChangedActionData
  | SourceStatusChangedActionData
  | ActorStatusChangedActionData
  | SharedModeChangedActionData
  | MonitoringTypeDetectedActionData
  | ReferenceSubjectDetectedActionData
  | ActorAddedActionData
  | SourceAddedActionData
  | UpdatedActionData

export interface WatchFileActivity {
  '@id': string
  '@type': 'WatchFileActivity'
  id: string
  actionType: string
  createdAt: string
  actionData: WatchFileActivityActionData
  user: {
    '@id': string
    '@type': 'User'
    id: string
    displayName: string
    email: string
  }
  watchFile: {
    '@id': string
    '@type': 'WatchFile'
    id: string
    name: string
  }
}

export interface GroupedWatchFileActivityDto extends JsonLdResource {
  '@type': 'GroupedWatchFileActivityDto'
  activitiesByDay: Record<string, WatchFileActivity[]>
  totalActivities: number
  totalItems: number
  hasNextPage: boolean
}
