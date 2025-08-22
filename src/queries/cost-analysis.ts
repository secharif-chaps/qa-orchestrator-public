import { defineQueryOptions } from '@pinia/colada'
import { costAnalysisApi, type CostAnalysisFilters } from '@/api/cost-analysis'

// Query keys for cache management
export const COST_ANALYSIS_QUERY_KEYS = {
  root: ['cost-analysis'] as const,
  global: (filters: CostAnalysisFilters) => [...COST_ANALYSIS_QUERY_KEYS.root, 'global', filters] as const,
  byWorkspace: (filters: CostAnalysisFilters) => [...COST_ANALYSIS_QUERY_KEYS.root, 'workspace', filters] as const,
  byTaskType: (filters: CostAnalysisFilters) => [...COST_ANALYSIS_QUERY_KEYS.root, 'task-type', filters] as const,
  trends: (filters: CostAnalysisFilters) => [...COST_ANALYSIS_QUERY_KEYS.root, 'trends', filters] as const,
}

// Global cost overview query
export const globalCostQuery = defineQueryOptions((filters: CostAnalysisFilters = {}) => ({
  key: COST_ANALYSIS_QUERY_KEYS.global(filters),
  query: () => costAnalysisApi.getGlobalCosts(filters),
}))

// Cost by workspace query
export const workspaceCostQuery = defineQueryOptions((filters: CostAnalysisFilters = {}) => ({
  key: COST_ANALYSIS_QUERY_KEYS.byWorkspace(filters),
  query: () => costAnalysisApi.getCostsByWorkspace(filters),
}))

// Cost by task type query
export const taskTypeCostQuery = defineQueryOptions((filters: CostAnalysisFilters = {}) => ({
  key: COST_ANALYSIS_QUERY_KEYS.byTaskType(filters),
  query: () => costAnalysisApi.getCostsByTaskType(filters),
}))

// Cost trends query
export const costTrendsQuery = defineQueryOptions((filters: CostAnalysisFilters = {}) => ({
  key: COST_ANALYSIS_QUERY_KEYS.trends(filters),
  query: () => costAnalysisApi.getCostTrends(filters),
}))