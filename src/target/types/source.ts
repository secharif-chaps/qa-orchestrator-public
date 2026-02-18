import type { Actor } from './actor'
import type { JsonLdResource } from './jsonld'
import type { Localized } from './localized'
import type { User } from './user'

export type SourceCategory = 'news' | 'social' | 'research' | 'business' | 'other'
export type SourceCapability = 'realtime' | 'api' | 'free' | 'premium' | 'rss' | 'webhook'
export enum SourceStatus {
  AVAILABLE = 'available',
  CONFIGURED = 'configured',
  ACTIVE = 'active',
  INACTIVE = 'inactive',
  AUTO_DISABLED = 'auto_disabled',
}
export enum CollectorStatus {
  STOPPED = 'stopped',
  RUNNING = 'running',
  ERROR = 'error',
}

export interface Source extends JsonLdResource {
  id: string
  name: string
  description: Localized
  type: string
  url: string
  primaryDomain: string
  relevance: Localized
  status: SourceStatus
  actor?: Actor
  createdAt: string
  updatedAt?: string
  collectStatus: CollectorStatus
  active: boolean
}

export interface SourceConfiguration {
  sourceId: string
  settings: Record<string, unknown>
  credentials?: {
    apiKey?: string
    username?: string
    password?: string
    webhookUrl?: string
    oauth?: {
      clientId: string
      clientSecret: string
    }
  }
  filters?: {
    keywords?: string[]
    languages?: string[]
    dateRange?: {
      start: Date
      end: Date
    }
  }
}

export interface SourceFilter {
  searchQuery: string
  category: SourceCategory | 'all'
  capabilities: SourceCapability[]
  sortBy: 'popularity' | 'name' | 'lastUpdated'
  sortOrder: 'asc' | 'desc'
  page: number
  pageSize: number
}

export interface SourceTag {
  label: string
  icon: string
  color: string
}

export interface SourceStats {
  quality: number
  relevance: number
  popularity: number
}

export interface SourceGroup {
  type: string
  name: string
  title: string
  sources: Source[]
}

export interface SourcesGroupedResponse {
  groups: SourceGroup[]
  summary: {
    total: number
    error: number
    running: number
    stopped: number
  }
}

export enum SourceActionType {
  SOURCE_CONNECTED = 'source_connected',
  SOURCE_ERROR = 'source_error',
  SOURCE_RECOVERED = 'source_recovered',
  SOURCE_CONFIG_UPDATED = 'source_config_updated',
  SOURCE_ADDED_TO_WATCHFILE = 'source_added_to_watchfile',
  SOURCE_STATUS_CHANGED = 'source_status_changed',
  SOURCE_COLLECT_STATUS_CHANGED = 'source_collect_status_changed',
}

export interface SourceActivity {
  '@id': string
  '@type': 'SourceActivity'
  id: string
  source: string
  user: User
  actionType: SourceActionType
  actionData: Record<string, unknown>
  createdAt: string
}

export interface GroupedSourceActivityDto extends JsonLdResource {
  activitiesByDay: Record<string, SourceActivity[]>
  totalItems: number
}

export interface BatchChangeSourceStatusResponse {
  success: boolean
  message: string
  results: Array<Record<string, unknown>>
  errors: Array<Record<string, unknown>>
  total: number
  processed: number
  failed: number
}
