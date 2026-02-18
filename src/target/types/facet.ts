import type { JsonLdResource } from './jsonld'

export interface Actor {
  id: string
  label: string
  primaryDomain?: string
}

export interface Source {
  id: string
  name: string
  primaryDomain: string
}

export interface ActorFacet {
  actor: Actor
  count: number
}

export interface AnalysisActor {
  id: string
  name: string
  count: number
}

export type AnalysisActorFacet = ActorFacet | AnalysisActor

export interface SourceFacet {
  source: Source
  count: number
}

export interface EventFacet {
  type: string
  count: number
}

export interface StatusFacet {
  status: string
  count: number
}

export interface DomainFacet {
  domain: string
  count: number
}

export interface BaseFacets extends JsonLdResource {
  actors: ActorFacet[]
  sources: SourceFacet[]
}

export interface FacetsWithStatuses extends BaseFacets {
  statuses: StatusFacet[]
}

export interface FacetsWithValidationStatuses extends FacetsWithStatuses {
  validationStatuses: StatusFacet[]
}

export interface FacetsWithDomains extends BaseFacets {
  domains: DomainFacet[]
}

export interface AnalysisEventFacet {
  type: string
  count: number
}

export interface DateRangeFacet {
  maxStartDate?: string | null
  maxEndDate?: string | null
}
export interface AnalysisFacets {
  actors?: AnalysisActorFacet[]
  eventTypes?: AnalysisEventFacet[]
  dateRange?: DateRangeFacet
}

export const FILTER_CATEGORY_VALIDATIONS = 'validations'
export const FILTER_CATEGORY_DATES = 'dates'
export const FILTER_CATEGORY_ACTORS = 'actors'
export const FILTER_CATEGORY_SOURCES = 'sources'
export const FILTER_CATEGORY_EVENTS = 'events'

export type FilterCategory =
  | typeof FILTER_CATEGORY_VALIDATIONS
  | typeof FILTER_CATEGORY_DATES
  | typeof FILTER_CATEGORY_ACTORS
  | typeof FILTER_CATEGORY_SOURCES
  | typeof FILTER_CATEGORY_EVENTS
