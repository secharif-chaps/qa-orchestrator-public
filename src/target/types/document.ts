import type { DatesPeriod, FilterDates } from './filter';
import type { Localized } from './localized';
import type { Source } from './source';
import type { User } from './user';
import type { FacetsWithValidationStatuses, FacetsWithDomains } from './facet';

export type DocumentStatus = 'pending' | 'validated' | 'rejected';
export type ManualValidationStatus = 'accept' | 'refuse' | null;

export type AiValidationStatus =
  | 'pending'
  | 'validated'
  | 'rejected'
  | 'uncertain'
  | 'failed';

export enum SummaryStatus {
  pending = 'pending',
  completed = 'completed',
  failed = 'failed',
}

export type SummaryStatusType = keyof typeof SummaryStatus;

export type DocumentDateType = 'datePublish' | 'dateCollect';

export interface AIValidation {
  '@id': string;
  '@type': 'AIValidation';
  status: AiValidationStatus;
  confidenceScore: number;
  validationReason: Localized;
  referenceSubject: Localized;
  processedAt: string;
}

export interface Document {
  '@id': string;
  '@type': string;
  id: string;
  title: string;
  excerpt: string;
  content: string;
  type: string;
  datePublish: string;
  dateCollect: string;
  status: DocumentStatus;
  updatedBy: User;
  validatedBy?: User;
  validatedAt?: string;
  language: string;
  insight?: string;
  cfcRestricted: boolean;
  updatedAt: string;
  source: Source;
  sourceId: string;
  watchFileId: string;
  manualStatus: ManualValidationStatus;
  summary: Localized;
  summaryStatus: SummaryStatusType;
  summaryGeneratedAt: string;
  aiValidation?: AIValidation;
  isSeen: boolean;
  manuallyAccepted: boolean;
  manuallyRefused: boolean;
  contentHighlighted: boolean;
  excerptHighlighted: boolean;
  url?: string;
}

// Re-export generic types for backward compatibility
export type { Actor as DocumentActor, Source as DocumentSource } from './facet';
export type {
  ActorFacet,
  SourceFacet,
  StatusFacet,
  DomainFacet,
  BaseFacets,
  FacetsWithStatuses,
  FacetsWithValidationStatuses,
  FacetsWithDomains,
} from './facet';

// Document-specific facets (includes all possible facets for documents)
export interface DocumentFacets
  extends FacetsWithValidationStatuses, FacetsWithDomains {}

export interface FilterParams {
  actors?: string[];
  sources?: string[];
  status?: string[];
  search?: string;
  datesPickerEnd?: string;
  datesPickerStart?: string;
  selectedDateType?: FilterDates;
  selectedPeriod?: DatesPeriod;
}

export interface CollectionParams extends FilterParams {
  sortBy: string;
  sortOrder: string;
  page?: number;
  itemsPerPage?: number;
}

export interface DocumentQueryOptions {
  watchFileId: string;
  filters: CollectionParams;
}

export interface DocumentValidationResponse {
  success: boolean;
  document_id: string;
  manual_status: string;
  validated_by: string;
  validated_at: string;
}

export interface BatchValidationResponse {
  success: boolean;
  validated_count: number;
  failed_count: number;
  results: Array<{
    document_id: string;
    status: 'success' | 'error';
    reason?: string;
  }>;
  validated_by: string;
  validated_at: string;
}

export enum DocumentValidationAction {
  ACCEPT = 'accept',
  REFUSE = 'refuse',
  UNCERTAIN = 'uncertain',
}
