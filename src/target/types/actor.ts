import type { SortOrder } from '@owlint/feathers-vue';
import type { Localized } from './localized';

export enum ActorStatus {
  ACTIVE = 'active',
  INACTIVE = 'inactive',
}

export interface Actor {
  id: string;
  '@id'?: string;
  label: string;
  primaryDomain?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface ActorDisplay {
  id: number;
  label: string;
  type: string;
  score: number;
  explanation: Localized;
}

export interface ActorFilters {
  status?: ActorStatus;
  search?: string;
  type?: string | string[];
  page?: number;
  itemsPerPage?: number;
  sortBy?: string;
  sortOrder?: SortOrder;
}

export interface ActorType {
  type: string;
  count: number;
}

export interface ActorTypesResponse {
  types: ActorType[];
}

export interface BatchChangeActorStatusResponse {
  success: boolean;
  message: string;
  results: Array<Record<string, unknown>>;
  errors: Array<Record<string, unknown>>;
  total: number;
  processed: number;
  failed: number;
}

export interface ActorSelection {
  actorId: string;
  sourceIds: string[];
}
