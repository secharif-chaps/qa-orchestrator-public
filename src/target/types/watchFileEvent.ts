import type { JsonLdCollectionView, JsonLdContext, JsonLdResource } from '~/types/jsonld'
import type { Localized } from './localized'

export interface WatchFileGraphEvent {
  '@type': string
  '@id': string
  documentsCount: number
  eventsCount: number
  hasEvents: boolean
  start: string
  end: string
  link: string
}

export interface EventActor extends JsonLdResource {
  id: string
  name: string
  role: string
}

export interface DocumentLink extends JsonLdResource {
  id: string
  textExtract: string
}

enum EventType {
  COMMERCIAL_BUSINESS = 'commercial_business',
  FINANCIAL = 'financial',
  ORGANIZATIONAL_HR = 'organizational_hr',
  TECHNOLOGICAL_RD = 'technological_rd',
  REGULATORY_POLITICAL = 'regulatory_political',
  MARKET_COMPETITORS = 'market_competitors',
  SOCIETAL_ENVIRONMENTAL = 'societal_environmental',
}

export interface WatchFileEvent extends JsonLdResource {
  id: string
  eventType: EventType
  extractionStatus: string
  startDate: string
  endDate: string
  description: Localized
  actors: EventActor[]
  documentLinks: DocumentLink[]
  createdAt: string
  title: Localized
}

export interface WatchFileEventCollection {
  '@context': string | JsonLdContext
  '@id': string
  '@type': string
  totalItems: number
  member: WatchFileEvent[]
  view?: JsonLdCollectionView
}

export interface WatchFileEventCollectionResponse {
  items: WatchFileEvent[]
  totalItems: number
  nextPageUrl?: string
}
